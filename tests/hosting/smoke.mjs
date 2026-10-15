import assert from 'node:assert/strict';
import https from 'node:https';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const credentialsPath = process.argv[2];
assert.ok(credentialsPath, 'Informe o caminho de ACESSO-GESTOR.txt do pacote de teste.');
const credentials = fs.readFileSync(credentialsPath, 'utf8');
const adminEmail = credentials.match(/E-mail: (.+)/)[1].trim();
const adminPassword = credentials.match(/Senha: (.+)/)[1].trim();
const compose = ['compose', '--env-file', 'output/hosting-check/compose.env', '-p', 'felito-hosting-check', '-f', 'tests/hosting/compose.yaml'];
const sql = query => execFileSync('docker', [...compose, 'exec', '-T', 'db', 'mariadb', '-uhosting', '-phosting_test_only', '-N', '-B', 'felito_hosting', '-e', query], { encoding: 'utf8' }).trim();

function client() {
  let cookie = '';
  return async (urlPath, fields = null, extra = {}) => {
    const body = fields === null ? '' : new URLSearchParams(fields).toString();
    const response = await new Promise((resolve, reject) => {
      const req = https.request({ hostname: 'localhost', port: 8443, path: urlPath, rejectUnauthorized: false,
        method: fields === null ? 'GET' : 'POST', headers: { Cookie: cookie, ...(fields === null ? {} : {
          'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(body),
        }), ...extra } }, res => {
        const chunks = [];
        res.on('data', chunk => chunks.push(chunk));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks).toString('utf8') }));
      });
      req.on('error', reject);
      req.end(body);
    });
    for (const header of response.headers['set-cookie'] ?? []) {
      if (header.startsWith('PHPSESSID=')) cookie = header.split(';')[0];
    }
    return response;
  };
}
const token = response => {
  const match = response.body.match(/name="_csrf" value="([a-f0-9]+)"/);
  assert.ok(match, 'Token CSRF ausente.');
  return match[1];
};
const customer = client();
const manager = client();
const catalog = await customer('/');
assert.equal(catalog.status, 200);
const sessionCookie = catalog.headers['set-cookie'].find(value => value.startsWith('PHPSESSID='));
assert.match(sessionCookie, /secure/i);
assert.match(sessionCookie, /httponly/i);
assert.match(sessionCookie, /samesite=lax/i);
assert.equal((await customer('/assets/style.css')).status, 200);
assert.equal((await customer('/favicon.svg')).status, 200);
assert.equal((await customer('/inexistente')).status, 404);
for (const route of ['/.env', '/config/config.php', '/app/bootstrap.php', '/views/layout.php', '/public/.env', '/database/schema.sql', '/scripts/generate-admin-sql.php', '/.git/config']) {
  assert.ok([403, 404].includes((await customer(route)).status), `Arquivo privado acessível: ${route}`);
}
assert.equal((await customer('/carrinho')).status, 200);
assert.equal((await customer('/entrar')).status, 200);
const protectedPage = await customer('/gestor/estoque');
assert.equal(protectedPage.status, 303);
assert.equal(protectedPage.headers.location, '/entrar');
assert.equal((await customer('/carrinho', { action: 'add', product_id: '1' })).status, 403);
const redirect = await new Promise((resolve, reject) => {
  const req = http.request({ hostname: 'localhost', port: 8082, path: '/checkout?teste=1', method: 'POST', headers: { Host: 'localhost:8443' } }, res => { res.resume(); resolve(res); });
  req.on('error', reject); req.end();
});
assert.equal(redirect.statusCode, 308);
assert.equal(redirect.headers.location, 'https://localhost:8443/checkout?teste=1');
console.log('OK: rotas, arquivos privados, CSRF, cookies e redirecionamento HTTPS.');

const newCustomer = client();
const registration = await newCustomer('/cadastro');
const email = `hosting-${Date.now()}@felito.example`;
const registered = await newCustomer('/cadastro', { _csrf: token(registration), name: 'Validação hospedagem', email, phone: '11999999999', password: 'ClienteTeste123!' });
assert.equal(registered.status, 303);
const initialStock = Number(sql('SELECT stock_qty FROM products WHERE id=1'));
let currentPage = await newCustomer('/');
assert.equal((await newCustomer('/carrinho', { _csrf: token(currentPage), action: 'add', product_id: '1' })).status, 303);
currentPage = await newCustomer('/checkout');
assert.equal(currentPage.status, 200);
const createOrder = await newCustomer('/checkout', { _csrf: token(currentPage), pickup_date: '2099-01-01', note: 'Pedido de validação' });
assert.equal(createOrder.status, 303);
const orderId = Number(createOrder.headers.location.match(/novo=(\d+)/)[1]);
assert.equal(Number(sql('SELECT stock_qty FROM products WHERE id=1')), initialStock - 1);
assert.ok((await newCustomer('/pedidos')).body.includes(`Encomenda #${orderId}`), 'Pedido ausente do histórico do cliente.');
assert.equal(sql(`SELECT ABS(TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP())) < 30 FROM orders WHERE id=${orderId}`), '1');

let login = await manager('/entrar');
assert.equal((await manager('/entrar', { _csrf: token(login), email: adminEmail, password: adminPassword })).status, 303);
assert.equal((await newCustomer('/gestor')).headers.location, '/');
const product = JSON.parse(sql("SELECT JSON_OBJECT('name',name,'description',description,'size_label',size_label,'price',price,'min_stock',min_stock,'is_active',is_active,'is_featured',is_featured) FROM products WHERE id=1"));
for (const name of [product.name + ' validado', product.name]) {
  currentPage = await manager('/gestor/produtos?editar=1');
  const input = { _csrf: token(currentPage), product_id: '1', name, description: product.description, size_label: product.size_label, price: product.price, min_stock: product.min_stock };
  if (product.is_active) input.is_active = '1';
  if (product.is_featured) input.is_featured = '1';
  assert.equal((await manager('/gestor/produtos', input)).status, 303);
  assert.equal(sql('SELECT name FROM products WHERE id=1'), name);
}
const displayedDate = sql(`SELECT DATE_FORMAT(created_at - INTERVAL 3 HOUR, '%d/%m/%Y %H:%i') FROM orders WHERE id=${orderId}`);
assert.ok((await manager('/gestor')).body.includes(displayedDate), 'Horário de São Paulo incorreto na interface.');
for (const status of ['preparing', 'ready', 'delivered']) {
  currentPage = await manager('/gestor');
  const changed = await manager('/gestor', { _csrf: token(currentPage), order_id: orderId, action: 'status', status });
  assert.equal(changed.status, 303);
  assert.equal(sql(`SELECT status FROM orders WHERE id=${orderId}`), status);
}
assert.match((await manager('/gestor?status=ready')).body, /value="ready" selected/);
currentPage = await newCustomer('/');
await newCustomer('/carrinho', { _csrf: token(currentPage), action: 'add', product_id: '1' });
currentPage = await newCustomer('/checkout');
const secondOrder = await newCustomer('/checkout', { _csrf: token(currentPage), pickup_date: '2099-01-01', note: 'Cancelar' });
const cancelId = Number(secondOrder.headers.location.match(/novo=(\d+)/)[1]);
currentPage = await manager('/gestor');
assert.equal((await manager('/gestor', { _csrf: token(currentPage), order_id: cancelId, action: 'cancel' })).status, 303);
assert.equal(sql(`SELECT status FROM orders WHERE id=${cancelId}`), 'cancelled');
assert.equal(Number(sql('SELECT stock_qty FROM products WHERE id=1')), initialStock - 1);
currentPage = await manager('/gestor/estoque');
await manager('/gestor/estoque', { _csrf: token(currentPage), product_id: '1', mode: 'entry', quantity: '2', reason: 'Produção de validação' });
assert.equal(Number(sql('SELECT stock_qty FROM products WHERE id=1')), initialStock + 1);
currentPage = await manager('/gestor/estoque');
await manager('/gestor/estoque', { _csrf: token(currentPage), product_id: '1', mode: 'adjustment', quantity: String(initialStock), reason: 'Contagem de validação' });
assert.equal(Number(sql('SELECT stock_qty FROM products WHERE id=1')), initialStock);
currentPage = await newCustomer('/pedidos');
assert.equal((await newCustomer('/sair', { _csrf: token(currentPage) })).status, 303);
login = await newCustomer('/entrar');
assert.equal((await newCustomer('/entrar', { _csrf: token(login), email, password: 'ClienteTeste123!' })).status, 303);
assert.equal((await newCustomer('/pedidos')).status, 200);
console.log('OK: cadastro, login, permissões, produtos, pedido, estoque, entrega, cancelamento, filtros e horários.');

// Falha controlada somente na cópia do pacote usada pelo banco descartável.
const fixtureEnv = path.resolve('output/hosting-check/htdocs/.env');
const original = fs.readFileSync(fixtureEnv, 'utf8');
try {
  fs.writeFileSync(fixtureEnv, original.replace('DB_NAME=felito_hosting', 'DB_NAME=banco_inexistente'));
  const failure = await customer('/');
  assert.equal(failure.status, 500);
  assert.match(failure.body, /Ocorreu um problema/);
  assert.doesNotMatch(failure.body, /SQLSTATE|PDOException|banco_inexistente|Stack trace/);
} finally { fs.writeFileSync(fixtureEnv, original); }
console.log('OK: falhas em produção retornam mensagem genérica sem detalhes internos.');

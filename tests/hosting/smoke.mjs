import assert from 'node:assert/strict';
import https from 'node:https';
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { performance } from 'node:perf_hooks';

const credentialsPath = process.argv[2];
assert.ok(credentialsPath, 'Informe o caminho de ACESSO-GESTOR.txt do pacote de teste.');
const credentials = fs.readFileSync(credentialsPath, 'utf8');
const adminEmail = credentials.match(/E-mail: (.+)/)[1].trim();
const adminPassword = credentials.match(/Senha: (.+)/)[1].trim();
const checkRoot = process.env.FELITO_HOSTING_CHECK_ROOT ?? 'output/hosting-check';
const compose = ['compose', '--env-file', `${checkRoot}/compose.env`, '-p', 'felito-hosting-check', '-f', 'tests/hosting/compose.yaml'];
const sql = query => execFileSync('docker', [...compose, 'exec', '-T', 'db', 'mariadb', '-uhosting', '-phosting_test_only', '-N', '-B', 'felito_hosting', '-e', query], { encoding: 'utf8' }).trim();

function client() {
  let cookie = '';
  return async (urlPath, fields = null, extra = {}) => {
    const body = fields === null ? '' : new URLSearchParams(fields).toString();
    const startedAt = performance.now();
    const response = await new Promise((resolve, reject) => {
      const req = https.request({ hostname: 'localhost', port: 8443, path: urlPath, rejectUnauthorized: false,
        method: fields === null ? 'GET' : 'POST', headers: { Cookie: cookie, ...(fields === null ? {} : {
          'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(body),
        }), ...extra } }, res => {
        const chunks = [];
        res.on('data', chunk => chunks.push(chunk));
        res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks).toString('utf8'), elapsedMs: performance.now() - startedAt }));
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
const registrationCsrf = token(registration);
const registeredWithoutConsent = await newCustomer('/cadastro', { _csrf: registrationCsrf, name: 'Hosting validation', email, phone: '11999999999', password: 'ClienteTeste123!' });
assert.equal(registeredWithoutConsent.status, 303);
assert.equal(sql(`SELECT COUNT(*) FROM users WHERE email='${email}'`), '0', 'Cadastro sem consentimento não pode criar usuário.');
const registered = await newCustomer('/cadastro', { _csrf: registrationCsrf, name: 'Hosting validation', email, phone: '11999999999', password: 'ClienteTeste123!', accept_terms: '1' });
assert.equal(registered.status, 303);
assert.ok(registered.elapsedMs < 3000, `Cadastro levou ${registered.elapsedMs.toFixed(0)} ms.`);
assert.equal(sql(`SELECT COUNT(*) FROM consent_records WHERE user_id=(SELECT id FROM users WHERE email='${email}')`), '1');
const initialStock = Number(sql('SELECT stock_qty FROM products WHERE id=1'));
let currentPage = await newCustomer('/');
assert.equal((await newCustomer('/carrinho', { _csrf: token(currentPage), action: 'add', product_id: '1' })).status, 303);
currentPage = await newCustomer('/checkout');
assert.equal(currentPage.status, 200);
const createOrder = await newCustomer('/checkout', { _csrf: token(currentPage), pickup_date: '2099-01-01', note: 'Pedido de validação' });
assert.equal(createOrder.status, 303);
assert.ok(createOrder.elapsedMs < 3000, `Validação do pedido levou ${createOrder.elapsedMs.toFixed(0)} ms.`);
const orderId = Number(createOrder.headers.location.match(/novo=(\d+)/)[1]);
assert.equal(Number(sql('SELECT stock_qty FROM products WHERE id=1')), initialStock - 1);
assert.ok((await newCustomer('/pedidos')).body.includes(`Encomenda #${orderId}`), 'Pedido ausente do histórico do cliente.');
assert.equal(sql(`SELECT ABS(TIMESTAMPDIFF(SECOND, created_at, UTC_TIMESTAMP())) < 30 FROM orders WHERE id=${orderId}`), '1');
assert.equal(sql(`SELECT TIMESTAMPDIFF(DAY, created_at, reserved_until) FROM orders WHERE id=${orderId}`), '5', 'Reserva precisa durar cinco dias.');
const privacyPage = await newCustomer('/privacidade');
assert.equal(privacyPage.status, 200);
assert.equal(JSON.parse((await newCustomer('/privacidade/exportar')).body).account.email, email);
assert.equal((await newCustomer('/privacidade', { _csrf: token(privacyPage), action: 'request', request_type: 'deletion', details: 'Solicitação de teste' })).status, 303);

let login = await manager('/entrar');
const managerLogin = await manager('/entrar', { _csrf: token(login), email: adminEmail, password: adminPassword });
assert.equal(managerLogin.status, 303);
assert.ok(managerLogin.elapsedMs < 3000, `Login levou ${managerLogin.elapsedMs.toFixed(0)} ms.`);
assert.equal((await newCustomer('/gestor')).headers.location, '/');
const managerDashboard = await manager('/gestor');
for (const label of ['Pedidos recebidos', 'Em andamento', 'Reservas próximas do prazo', 'Estoque abaixo do mínimo', 'Brindes pendentes', 'Pedidos expirados · 30 dias', 'Solicitações de privacidade']) {
  assert.ok(managerDashboard.body.includes(label), `Indicador do dashboard ausente: ${label}`);
}
let loyaltyPage = await manager('/gestor/fidelidade');
assert.equal((await manager('/gestor/fidelidade', { _csrf: token(loyaltyPage), action: 'settings', points_per_real: '1.00' })).status, 303);
loyaltyPage = await manager('/gestor/fidelidade');
assert.equal((await manager('/gestor/fidelidade', { _csrf: token(loyaltyPage), action: 'reward', name: 'Brinde de teste', description: 'Recompensa descartável', points_cost: '10' })).status, 303);
const rewardId = Number(sql("SELECT id FROM loyalty_rewards WHERE name='Brinde de teste'"));
const product = JSON.parse(sql("SELECT JSON_OBJECT('name',name,'description',description,'image_path',image_path,'size_label',size_label,'price',price,'min_stock',min_stock,'is_active',is_active,'is_featured',is_featured) FROM products WHERE id=1"));
for (const name of [product.name + ' validado', product.name]) {
  currentPage = await manager('/gestor/produtos?editar=1');
  const input = { _csrf: token(currentPage), product_id: '1', name, description: product.description, image_path: product.image_path, size_label: product.size_label, price: product.price, min_stock: product.min_stock };
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
assert.equal(Number(sql(`SELECT SUM(points_delta) FROM loyalty_movements WHERE order_id=${orderId}`)), 32, 'Pontos devem ser creditados na entrega.');
currentPage = await newCustomer('/fidelidade');
assert.equal((await newCustomer('/fidelidade', { _csrf: token(currentPage), reward_id: String(rewardId) })).status, 303);
assert.equal(Number(sql(`SELECT SUM(points_delta) FROM loyalty_movements WHERE user_id=(SELECT id FROM users WHERE email='${email}')`)), 22);
assert.equal(Number(sql("SELECT COUNT(*) FROM loyalty_redemptions WHERE status='pending'")), 1);
currentPage = await manager('/gestor/fidelidade');
const redemptionId = Number(sql("SELECT id FROM loyalty_redemptions WHERE status='pending'"));
assert.equal((await manager('/gestor/fidelidade', { _csrf: token(currentPage), action: 'delivered', redemption_id: String(redemptionId) })).status, 303);
assert.equal(sql(`SELECT status FROM loyalty_redemptions WHERE id=${redemptionId}`), 'delivered');
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
currentPage = await manager('/gestor/privacidade');
assert.ok(currentPage.body.includes(email));
const requestId = Number(sql("SELECT id FROM privacy_requests WHERE details='Solicitação de teste'"));
assert.equal((await manager('/gestor/privacidade', { _csrf: token(currentPage), request_id: String(requestId), status: 'completed' })).status, 303);
assert.equal(sql(`SELECT status FROM privacy_requests WHERE id=${requestId}`), 'completed');
currentPage = await manager('/gestor/estoque');
await manager('/gestor/estoque', { _csrf: token(currentPage), action: 'close_month', year_month: '2026-09' });
assert.equal(Number(sql("SELECT COUNT(*) FROM monthly_stock_closures WHERE period_month='2026-09'")), 3);
currentPage = await manager('/gestor/estoque');
await manager('/gestor/estoque', { _csrf: token(currentPage), product_id: '1', mode: 'adjustment', quantity: '3', reason: 'Alerta de teste' });
await manager('/gestor/estoque');
assert.equal(sql('SELECT active FROM low_stock_alerts WHERE product_id=1'), '1');
currentPage = await manager('/gestor/estoque');
await manager('/gestor/estoque', { _csrf: token(currentPage), product_id: '1', mode: 'adjustment', quantity: String(initialStock), reason: 'Limpar alerta de teste' });
await manager('/gestor/estoque');
assert.equal(sql('SELECT active FROM low_stock_alerts WHERE product_id=1'), '0');
currentPage = await newCustomer('/');
await newCustomer('/carrinho', { _csrf: token(currentPage), action: 'add', product_id: '1' });
currentPage = await newCustomer('/checkout');
const expiringOrder = await newCustomer('/checkout', { _csrf: token(currentPage), pickup_date: '2099-01-01', note: 'Expirar reserva' });
const expiredId = Number(expiringOrder.headers.location.match(/novo=(\d+)/)[1]);
sql(`UPDATE orders SET reserved_until=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE id=${expiredId}`);
await newCustomer('/');
assert.equal(sql(`SELECT status FROM orders WHERE id=${expiredId}`), 'expired');
assert.equal(Number(sql('SELECT stock_qty FROM products WHERE id=1')), initialStock);
currentPage = await newCustomer('/pedidos');
assert.equal((await newCustomer('/sair', { _csrf: token(currentPage) })).status, 303);
login = await newCustomer('/entrar');
assert.equal((await newCustomer('/entrar', { _csrf: token(login), email, password: 'ClienteTeste123!' })).status, 303);
assert.equal((await newCustomer('/pedidos')).status, 200);
console.log(`OK: cadastro ${registered.elapsedMs.toFixed(0)} ms, login ${managerLogin.elapsedMs.toFixed(0)} ms, validação do pedido ${createOrder.elapsedMs.toFixed(0)} ms (< 3000 ms); permissões, produtos, reserva, fidelidade, privacidade, estoque, entrega, cancelamento e horários.`);

// Falha controlada somente na cópia do pacote usada pelo banco descartável.
const fixtureEnv = path.resolve(checkRoot, 'htdocs/.env');
const original = fs.readFileSync(fixtureEnv, 'utf8');
try {
  fs.writeFileSync(fixtureEnv, original.replace('DB_NAME=felito_hosting', 'DB_NAME=banco_inexistente'));
  const failure = await customer('/');
  assert.equal(failure.status, 500);
  assert.match(failure.body, /Ocorreu um problema/);
  assert.doesNotMatch(failure.body, /SQLSTATE|PDOException|banco_inexistente|Stack trace/);
} finally { fs.writeFileSync(fixtureEnv, original); }
console.log('OK: falhas em produção retornam mensagem genérica sem detalhes internos.');

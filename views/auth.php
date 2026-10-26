<?php $title = $mode === 'login' ? 'Entrar — Felito' : 'Criar conta — Felito'; ?>
<section class="narrow-layout">
  <div class="panel auth-panel">
    <div class="eyebrow"><span class="dot"></span><?= $mode === 'login' ? 'Que bom ter você de volta' : 'Um passo mais perto do seu sabor favorito' ?></div>
    <h1><?= $mode === 'login' ? 'Entrar' : 'Criar sua conta' ?></h1>
    <p class="panel-intro"><?= $mode === 'login' ? 'Acesse seus pedidos e continue sua encomenda.' : 'Usamos seus dados para acompanhar e confirmar sua retirada.' ?></p>
    <form method="post" action="<?= $mode === 'login' ? '/entrar' : '/cadastro' ?>" class="form-stack">
      <?= csrfField() ?>
      <?php if ($mode === 'register'): ?>
        <label for="name">Nome completo</label><input id="name" name="name" type="text" autocomplete="name" maxlength="120" required value="<?= e($old['name'] ?? '') ?>">
      <?php endif ?>
      <label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="email" maxlength="190" required value="<?= e($old['email'] ?? '') ?>">
      <?php if ($mode === 'register'): ?>
        <label for="phone">Telefone para confirmação</label><input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="30" required value="<?= e($old['phone'] ?? '') ?>">
      <?php endif ?>
      <label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>" minlength="<?= $mode === 'login' ? '1' : '10' ?>" maxlength="72" required>
      <?php if ($mode === 'register'): ?><p class="field-help">Use pelo menos 10 caracteres.</p><label class="check-row"><input type="checkbox" name="accept_terms" value="1" required><span>Li e aceito os <a href="/termos" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/politica-de-privacidade" target="_blank" rel="noopener">Política de Privacidade</a>.</span></label><?php endif ?>
      <button class="button button-primary button-full" type="submit"><?= $mode === 'login' ? 'Entrar na minha conta' : 'Criar conta e continuar' ?></button>
    </form>
    <p class="form-switch"><?= $mode === 'login' ? 'Ainda não tem conta? <a href="/cadastro">Cadastre-se</a>.' : 'Já tem uma conta? <a href="/entrar">Entre aqui</a>.' ?></p>
  </div>
</section>

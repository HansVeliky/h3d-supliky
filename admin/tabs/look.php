<?php
/**
 * Administration tab: look
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }
?>
  <form method="post" class="card" id="palette-settings-form">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="tab" value="look">
    <input type="hidden" name="_flags" value="">
    <input type="hidden" name="_texts" value="">
    <input type="hidden" name="_palette_preset" value="1">
<?php
$paletteFields = [
  'palette_bg'=>['Pozadí','Background'], 'palette_panel'=>['Plochy','Panels'],
  'palette_panel2'=>['Druhé plochy','Secondary panels'], 'palette_line'=>['Ohraničení','Borders'],
  'palette_text'=>['Hlavní text','Main text'], 'palette_muted'=>['Vedlejší text','Muted text'],
  'palette_accent'=>['Zvýraznění','Accent'], 'palette_accent_dark'=>['Tmavší zvýraznění','Dark accent'],
  'palette_accent_text'=>['Text zvýraznění','Accent text'], 'palette_button'=>['Pozadí tlačítka','Button background'],
  'palette_button_text'=>['Text tlačítka','Button text'], 'palette_button_hover'=>['Tlačítko po najetí','Button hover'],
  'palette_good'=>['Potvrzovací tlačítko','Confirm button'], 'palette_good_text'=>['Text potvrzovacího tlačítka','Confirm text'],
  'palette_good_hover'=>['Potvrzovací tlačítko po najetí','Confirm hover'], 'palette_bad'=>['Varování','Warning'],
  'palette_canvas'=>['Pozadí kresby','Canvas background'], 'palette_grid'=>['Mřížka','Grid'],
];
?>
<fieldset class="palette-admin">
  <legend>Paleta barev</legend>
  <p class="hint" style="margin-top:0">Paleta určuje barvy celého webu. Nastavením barev určíš vzhled celého webu.</p>
  <div class="palette-presets" role="group" aria-label="Paleta">
    <button type="button" class="palette-preset is-on" data-palette-preset="light"><span class="swatches"><i></i><i></i><i></i></span><b>H3D Light</b></button>
  </div>
  <div class="palette-grid">
<?php foreach ($paletteFields as $key => [$cs,$en]): ?>
    <label class="palette-color"><span><?= e($cs) ?></span><input type="color" name="<?= e($key) ?>" value="<?= e(Settings::get($key)) ?>" data-palette-color="<?= e($key) ?>"><code><?= e(Settings::get($key)) ?></code></label>
<?php endforeach; ?>
  </div>
  <p class="hint">Změna barvy se ukládá automaticky po potvrzení výběru. Není potřeba nic dalšího ukládat.</p>
</fieldset>




<fieldset>
      <legend>Okraje stránky</legend>
      <p class="hint" style="margin-top:0">
        Rezerva pro pruh, který vkládá hosting (reklama). Web se o tolik
        zkrátí, takže mu nic nezůstane pod pruhem. Web si výšku pruhu měří
        i sám; tohle je navíc, když by měření nestačilo.
      </p>
      <div class="grid2">
        <div>
          <label for="page_margin_top">Horní okraj (px)</label>
          <input id="page_margin_top" name="page_margin_top" type="number" min="0" max="300"
                 value="<?= Settings::int('page_margin_top') ?>">
        </div>
        <div>
          <label for="page_margin_bottom">Spodní okraj (px)</label>
          <input id="page_margin_bottom" name="page_margin_bottom" type="number" min="0" max="300"
                 value="<?= Settings::int('page_margin_bottom') ?>">
        </div>
      </div>
    </fieldset>
  </form>

<?php if ($tab === 'look'): ?>
<style>
.palette-admin{margin-bottom:18px}.palette-presets{display:grid;grid-template-columns:1fr;gap:10px;margin:12px 0 16px}.palette-preset{display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto auto;column-gap:10px;align-items:center;text-align:left;padding:11px 12px;border:1px solid var(--line,#d5d9df);border-radius:11px;background:var(--panel2,#f5f7fa);color:var(--text,#20242a);cursor:pointer}.palette-preset.is-on{outline:2px solid var(--accent,#1769d1);border-color:var(--accent,#1769d1)}.palette-preset b{font-size:13px}.palette-preset small{color:var(--muted,#68707a);font-size:10px}.swatches{grid-row:1/3;display:flex;overflow:hidden;width:44px;height:24px;border-radius:6px;border:1px solid var(--line,#d5d9df)}.swatches i{flex:1}.swatches i:nth-child(1){background:#eef1f5}.swatches i:nth-child(2){background:#f7f9fc}.swatches i:nth-child(3){background:#1769d1}.palette-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.palette-color{display:grid;grid-template-columns:1fr auto;align-items:center;gap:6px;min-height:52px;padding:7px 8px;border:1px solid var(--line,#d5d9df);border-radius:9px;background:var(--panel2,#f5f7fa);color:var(--text,#20242a)}.palette-color span{font-size:10px;line-height:1.15}.palette-color input{width:36px;height:30px;padding:2px;border:1px solid var(--line,#d5d9df);background:transparent;border-radius:6px}.palette-color code{grid-column:1/-1;font-size:9px;color:var(--muted,#68707a)}@media(max-width:1000px){.palette-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:650px){.palette-presets,.palette-grid{grid-template-columns:1fr}}
</style>
<script>
(()=> {
  const presets = {
    light: {
      palette_bg:'#eef1f5', palette_panel:'#ffffff', palette_panel2:'#f5f7fa',
      palette_line:'#d5dce5', palette_text:'#20242a', palette_muted:'#68707a',
      palette_accent:'#1769d1', palette_accent_dark:'#0f57b4',
      palette_accent_text:'#ffffff', palette_button:'#e8eef5',
      palette_button_text:'#20242a', palette_button_hover:'#dbe5ef',
      palette_good:'#168b57', palette_good_text:'#ffffff',
      palette_good_hover:'#117247', palette_bad:'#d64555',
      palette_canvas:'#eef1f5', palette_grid:'#b5c1cf'
    },};

  const form = document.querySelector('.palette-admin')?.closest('form');
    const body = document.body;
  const root = document.documentElement;

  const cssMap = {
    palette_bg:'--bg', palette_panel:'--panel', palette_panel2:'--panel2',
    palette_line:'--line', palette_text:'--text', palette_muted:'--muted',
    palette_accent:'--accent', palette_accent_dark:'--accentv2',
    palette_accent_text:'--accentText', palette_button:'--button',
    palette_button_text:'--buttonText', palette_button_hover:'--buttonHover',
    palette_good:'--good', palette_good_text:'--goodText',
    palette_good_hover:'--goodHover', palette_bad:'--bad',
    palette_canvas:'--canvas-bg', palette_grid:'--grid-color'
  };

  function applyPalettePreview(values, selected) {
    
    Object.entries(values).forEach(([name, value]) => {
      const cssVar = cssMap[name];
      if (cssVar) root.style.setProperty(cssVar, value);
      const input = document.querySelector('[data-palette-color="' + name + '"]');
      if (input) {
        input.value = value;
        const code = input.parentElement?.querySelector('code');
        if (code) code.textContent = value.toUpperCase();
      }
    });

    root.classList.add('h3d-light-admin');
    body.classList.add('h3d-light-admin');
    root.style.colorScheme = 'light';


    document.querySelectorAll('[data-palette-preset]').forEach(x => {
      x.classList.toggle('is-on', x === selected);
    });
  }

  let saving = false;
  function persistPalette() {
    if (!form || saving) return;
    saving = true;
    const marker = form.querySelector('input[name="_palette_preset"]');
    if (marker) marker.value = '0';
    // Use the native submit method deliberately. This is an autosave form:
    // it must not be blocked by unrelated validation or another submit
    // listener on the administration panel.
    HTMLFormElement.prototype.submit.call(form);
  }

  document.querySelectorAll('[data-palette-preset]').forEach(button => {
    button.addEventListener('click', () => {
            const values = {...presets.light};
      applyPalettePreview(values, button);

      // Preset selection is itself a save operation.
      if (form) {
        const marker = form.querySelector('input[name="_palette_preset"]');
        if (marker) marker.value = '1';
        persistPalette();
      }
    });
  });

  document.querySelectorAll('[data-palette-color]').forEach(input => {
    // Náhled se mění okamžitě během práce s pickerem.
    input.addEventListener('input', () => {
      const cssVar = cssMap[input.dataset.paletteColor];
      if (cssVar) root.style.setProperty(cssVar, input.value);
      const code = input.parentElement?.querySelector('code');
      if (code) code.textContent = input.value.toUpperCase();
    });

    // Uložení proběhne automaticky po potvrzení nové barvy.
    // Používáme change místo input, aby se neposílal POST při každém
    // drobném pohybu myši uvnitř color pickeru.
    input.addEventListener('change', () => {
      persistPalette();
    });
  });
})();
</script>
<?php endif; ?>

<?php
/**
 * Administration tab: exports
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    // Everything on this screen answers one question - what does an export
    // cost a given person - so the screen starts by answering it outright,
    // and only then offers the knobs, grouped by who they affect.
    $cUser  = Settings::int('cooldown_user');
    $cGuest = Settings::int('cooldown_guest');
    $cUserMin  = (int) round($cUser / 60);
    $cGuestMin = (int) round($cGuest / 60);
    $fUser  = Settings::int('free_per_window_user');
    $fGuest = Settings::int('free_per_window_guest');
    $onUser = Settings::bool('free_enabled_user');
    $onGuest = Settings::bool('free_enabled_guest');
    $credOn = Settings::bool('credits_enabled');
    $cost   = Settings::int('credit_cost');

    /** One sentence describing what a person of this kind actually gets. */
    $summarise = static function (bool $freeOn, int $window, int $count) use ($credOn, $cost): string {
        $paid = $credOn
            ? 'další za ' . Cred::fmtCs($cost) . ' kreditů'
            : 'a víc nikdo nevyexportuje (kredity jsou vypnuté)';
        if (!$freeOn || $count === 0) {
            return $credOn
                ? 'žádný export zdarma, každý stojí ' . Cred::fmtCs($cost) . ' kreditů'
                : 'nevyexportuje vůbec nic - volné exporty i kredity jsou vypnuté';
        }
        if ($window === 0) {
            return 'neomezeně zdarma';
        }
        return $count . '× zdarma za ' . Quota::humanDuration($window) . ', ' . $paid;
    };

    $guestsAllowed = Settings::bool('guest_export_enabled') && !Settings::bool('require_login');
?>
  <div class="card">
    <h2>Jak to teď funguje</h2>
    <table class="ud-kv">
      <tr>
        <th>Přihlášený uživatel</th>
        <td><?= e($summarise($onUser, $cUser, $fUser)) ?></td>
      </tr>
      <tr>
        <th>Nepřihlášený návštěvník</th>
        <td>
          <?= $guestsAllowed
              ? e($summarise($onGuest, $cGuest, $fGuest))
              : 'exportovat nemůže - export bez přihlášení je vypnutý' ?>
        </td>
      </tr>
      <tr>
        <th>Neomezený přístup</th>
        <td>administrátoři, super uživatelé a účty s běžícím časovým plánem - vždy zdarma</td>
      </tr>
    </table>
    <?php if (!$onUser && !$credOn): ?>
      <div class="msg warn" style="margin-top:14px">
        Volné exporty jsou vypnuté a kredity taky, takže přihlášený uživatel
        nevyexportuje nic. Zapni jedno z toho níže.
      </div>
    <?php endif; ?>
  </div>

  <form method="post" data-busy="Ukládám…">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="_flags" value="guest_export_enabled,require_login,credits_enabled,confirm_credit_spend,free_enabled_user,free_enabled_guest,referral_enabled">
    <input type="hidden" name="_texts" value="">

    <div class="card">
      <h2>Exporty zdarma</h2>
      <p class="hint" style="margin-top:0">
        Okno je časový úsek v minutách, počet je kolik volných exportů se do něj vejde:
        „3 za 30 minut“ = okno 30, počet 3. Okno <code>0</code> znamená
        neomezeně zdarma, počet <code>0</code> žádný volný export.
      </p>

      <div class="grid2">
        <div>
          <label class="check">
            <input type="checkbox" name="free_enabled_user" value="1"
                   <?= $onUser ? 'checked' : '' ?>>
            <strong>Přihlášení</strong>
          </label>
          <label for="cooldown_user">Okno (minuty)</label>
          <input id="cooldown_user" name="cooldown_user" type="number" min="0"
                 value="<?= $cUserMin ?>">
          <label for="free_per_window_user">Volných exportů za okno</label>
          <input id="free_per_window_user" name="free_per_window_user" type="number" min="0"
                 value="<?= $fUser ?>">
          <div class="hint" data-window-hint="user"><?= e($summarise($onUser, $cUser, $fUser)) ?></div>
        </div>

        <div>
          <label class="check">
            <input type="checkbox" name="free_enabled_guest" value="1"
                   <?= $onGuest ? 'checked' : '' ?>>
            <strong>Nepřihlášení</strong>
          </label>
          <label for="cooldown_guest">Okno (minuty)</label>
          <input id="cooldown_guest" name="cooldown_guest" type="number" min="0"
                 value="<?= $cGuestMin ?>">
          <label for="free_per_window_guest">Volných exportů za okno</label>
          <input id="free_per_window_guest" name="free_per_window_guest" type="number" min="0"
                 value="<?= $fGuest ?>">
          <div class="hint" data-window-hint="guest"><?= e($summarise($onGuest, $cGuest, $fGuest)) ?></div>
        </div>
      </div>

      <label class="check" style="margin-top:14px">
        <input type="checkbox" name="guest_export_enabled" value="1"
               <?= Settings::bool('guest_export_enabled') ? 'checked' : '' ?>>
        Nepřihlášení smějí exportovat
      </label>
      <label class="check">
        <input type="checkbox" name="require_login" value="1"
               <?= Settings::bool('require_login') ? 'checked' : '' ?>>
        Celé studio jen po přihlášení
      </label>
    </div>

    <div class="card">
      <h2>Kredity</h2>
      <label class="check">
        <input type="checkbox" name="credits_enabled" value="1" <?= $credOn ? 'checked' : '' ?>>
        Používat kredity
      </label>
      <label class="check">
        <input type="checkbox" name="confirm_credit_spend" value="1" <?= Settings::bool('confirm_credit_spend') ? 'checked' : '' ?>>
        Ptát se před odečtením kreditu
      </label>
      <div class="grid2" style="margin-top:14px">
        <div>
          <label for="credit_cost">Cena jednoho exportu</label>
          <input id="credit_cost" name="credit_cost" type="number" min="0" step="0.1"
                 value="<?= e(Cred::input($cost)) ?>">
          <div class="hint">Kolik kreditů se strhne, když volný export došel.</div>
        </div>
        <div>
          <label for="daily_credits">Kredity každý den</label>
          <input id="daily_credits" name="daily_credits" type="number" min="0" step="0.1"
                 value="<?= e(Cred::input(Settings::int('daily_credits'))) ?>">
          <div class="hint">
            Připíší se po půlnoci při první návštěvě, <code>0</code> vypíná.
            Do půlnoci zbývá <?= e(Quota::humanDuration(DailyCredits::secondsUntilMidnight())) ?>.
          </div>
        </div>
        <div>
          <label for="daily_credits_cap">Strop pro denní kredity</label>
          <input id="daily_credits_cap" name="daily_credits_cap" type="number" min="0" step="0.1"
                 value="<?= e(Cred::input(Settings::int('daily_credits_cap'))) ?>">
          <div class="hint">Nad tímto zůstatkem se už nepřipisuje. <code>0</code> = bez stropu.</div>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Co dostane nový uživatel</h2>
      <div class="grid2">
        <div>
          <label for="signup_bonus">Za ověření e-mailu - kredity</label>
          <input id="signup_bonus" name="signup_bonus" type="number" min="0" step="0.1"
                 value="<?= e(Cred::input(Settings::int('signup_bonus'))) ?>">
        </div>
        <div>
          <label for="signup_bonus_days">Za ověření e-mailu - dny zdarma</label>
          <input id="signup_bonus_days" name="signup_bonus_days" type="number" min="0" step="1"
                 value="<?= Settings::int('signup_bonus_days') ?>">
          <div class="hint">Neomezené exporty na tolik dní. Můžeš dát kredity, dny, nebo obojí.</div>
        </div>
      </div>

      <label class="check" style="margin-top:14px">
        <input type="checkbox" name="referral_enabled" value="1" <?= Settings::bool('referral_enabled') ? 'checked' : '' ?>>
        Odměna za pozvání kamaráda
      </label>
      <div class="grid2">
        <div>
          <label for="referral_bonus">Kredity pro oba</label>
          <input id="referral_bonus" name="referral_bonus" type="number" min="0" step="0.1"
                 value="<?= e(Cred::input(Settings::int('referral_bonus'))) ?>">
          <div class="hint">Zvoucí i pozvaný, jakmile pozvaný ověří e-mail.</div>
        </div>
        <div>
          <label for="referral_max">Nejvýš odměn na účet</label>
          <input id="referral_max" name="referral_max" type="number" min="0" step="1"
                 value="<?= Settings::int('referral_max') ?>">
          <div class="hint"><code>0</code> = bez stropu. Pozvaný dostane bonus vždy.</div>
        </div>
      </div>
    </div>

  </form>

  <div class="card">
    <h2>Rozdat všem najednou</h2>
    <p class="hint" style="margin-top:0">
      Kredity, dny neomezeného exportu, nebo obojí zároveň. Zablokované
      účty, správce a super uživatele to přeskakuje. Dny se přičtou
      k běžícímu plánu, takže nikdo nepřijde o to, co má zaplacené.
    </p>
    <form method="post" data-busy="Rozdávám…">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="bulk_credits">
      <div class="grid3">
        <div>
          <label for="bulk_amount">Kredity</label>
          <input id="bulk_amount" name="amount" type="number" step="0.1" value="0">
          <div class="hint">Záporná hodnota kredity odebírá.</div>
        </div>
        <div>
          <label for="bulk_days">Dny neomezeného exportu</label>
          <input id="bulk_days" name="days" type="number" min="0" step="1" value="0">
          <div class="hint">0 = žádný časový plán nerozdávat.</div>
        </div>
        <div>
          <label for="bulk_scope">Komu</label>
          <select id="bulk_scope" name="scope">
            <option value="active">Všem aktivním účtům</option>
            <option value="verified">Jen ověřeným účtům</option>
          </select>
        </div>
      </div>
      <label for="bulk_reason">Důvod (nepovinné, uvidí ho uživatel v historii)</label>
      <input id="bulk_reason" name="reason" type="text">
      <button class="btn primary" type="submit" style="margin-top:14px"
              data-confirm="Rozdat tohle všem vybraným účtům? U kreditů odejde každému e-mail. Zpět to jedním klikem nevezmeš."
              data-confirm-ok="Rozdat všem">Rozdat</button>
    </form>
  </div>

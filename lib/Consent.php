<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Cookie consent.
 *
 * The rules this follows, because they are the ones that get sites fined:
 *
 *  - Nothing that needs consent runs before consent is given. Analytics is
 *    not loaded, not pre-loaded and not "loaded but paused" - the script is
 *    simply not on the page until somebody says yes.
 *  - Refusing is exactly as easy as accepting: both are buttons on the first
 *    screen, the same size, next to each other. No "accept" in colour and
 *    "manage preferences" in grey six clicks away.
 *  - The banner does not block the site. Ignoring it means nothing is
 *    stored, which is the same as refusing.
 *  - The answer can be changed at any time, from the same place the details
 *    are (the Cookies page), and the footer link is on every page.
 *  - Strictly necessary cookies - the session that keeps somebody signed in,
 *    the theme they chose, this consent record itself - are not asked about,
 *    because asking implies they could be refused, and they cannot: the site
 *    would stop working. They are listed on the Cookies page instead.
 *
 * With no analytics configured there is nothing to consent to, so the banner
 * never appears at all. That is deliberate: a site that only sets necessary
 * cookies asking for permission is noise, and it teaches people to click
 * "accept" without reading.
 */
final class Consent
{
    public const COOKIE = 'h3d_consent';

    /**
     * Bump when the set of cookies changes. The stored answer carries the
     * version it was given under, so a change means asking again rather than
     * quietly extending an old yes to something new.
     */
    private const VERSION = 1;

    private const YEAR = 31536000;

    /** The measurement ID, or '' when analytics is switched off entirely. */
    public static function analyticsId(): string
    {
        $id = trim(Settings::get('analytics_id'));

        // A wrong-looking id would just put a broken script on every page.
        return preg_match('/^(G-[A-Z0-9]{4,20}|UA-\d{4,12}-\d{1,4})$/i', $id) ? $id : '';
    }

    /** Is there anything worth asking about? */
    public static function needed(): bool
    {
        return self::analyticsId() !== '';
    }

    /** 'all', 'none', or '' when the question has not been answered yet. */
    public static function choice(): string
    {
        $raw = (string) ($_COOKIE[self::COOKIE] ?? '');
        if (!preg_match('/^(all|none):(\d+)$/', $raw, $m)) {
            return '';
        }

        // An answer given to an older list of cookies is not an answer to
        // this one.
        return (int) $m[2] === self::VERSION ? $m[1] : '';
    }

    public static function accepted(): bool
    {
        return self::needed() && self::choice() === 'all';
    }

    /** True when the banner should be on the page. */
    public static function shouldAsk(): bool
    {
        return self::needed() && self::choice() === '';
    }

    /**
     * The analytics snippet, or '' when it must not run.
     *
     * Consent mode is set as well as withholding the script: this site never
     * uses advertising or personalisation, and saying so explicitly means
     * Google is told too, not just trusted to work it out.
     */
    public static function analyticsTag(): string
    {
        if (!self::accepted()) {
            return '';
        }

        $id = e(self::analyticsId());

        // Google's own snippet, with the id from the panel dropped into both
        // places it belongs. The consent line is the only addition: this site
        // never advertises or personalises, and Google is told so rather than
        // left to assume.
        return <<<HTML
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=$id"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied',
    ad_personalization: 'denied', analytics_storage: 'granted'});
  gtag('js', new Date());

  gtag('config', '$id', {anonymize_ip: true});
</script>

HTML;
    }

    /**
     * The banner itself.
     *
     * Rendered server side and only when it is actually needed, so a visitor
     * who has answered - or a site with no analytics - never downloads it.
     */
    public static function banner(string $base = ''): string
    {
        if (!self::shouldAsk()) {
            return '';
        }

        $cs = Lang::current() === 'cs';

        $title = $cs ? 'Cookies' : 'Cookies';
        $text  = $cs
            ? 'Tenhle web funguje i bez souhlasu - nutné cookies jen drží přihlášení a tvoje '
              . 'nastavení; pokud při přihlášení zvolíš „Zůstat přihlášen 30 dní“, použije se navíc zabezpečený přihlašovací cookie. Tyto cookies nikam neodcházejí. Navíc bych rád měřil návštěvnost přes Google '
              . 'Analytics; to je jediné, co posílá data ven, a jen když to dovolíš.'
            : 'This site works without consent - the necessary cookies only keep you signed in '
              . 'and remember your settings. If you choose “Stay signed in for 30 days”, a secure persistent sign-in cookie is used as well. These cookies never leave the server. On top of that I would '
              . 'like to measure traffic with Google Analytics; that is the only thing that sends '
              . 'data anywhere, and only if you allow it.';

        $accept = $cs ? 'Přijmout' : 'Accept';
        $reject = $cs ? 'Odmítnout' : 'Reject';
        $more   = $cs ? 'Více informací' : 'More information';

        /*
         * The styles travel with the banner instead of living in a
         * stylesheet. It has to look right on the studio and on the panel
         * pages, and those load different CSS - one copy here beats two that
         * drift apart. Nobody who has answered ever downloads it.
         */
        $css = <<<CSS
<style>
.cookie-bar{position:fixed;left:16px;right:16px;bottom:16px;z-index:600;
  display:flex;align-items:center;gap:18px;flex-wrap:wrap;max-width:960px;margin:0 auto;
  padding:16px 20px;border:1px solid rgba(128,140,160,.35);border-radius:14px;
  background:#1b2130;color:#eef2f7;box-shadow:0 12px 40px rgba(0,0,0,.45);
  font:13.5px/1.55 Inter,system-ui,sans-serif}
body.light .cookie-bar{background:#fff;color:#20242a;border-color:#d5d9df;
  box-shadow:0 12px 40px rgba(20,40,70,.18)}
.cookie-bar-text{flex:1 1 320px;min-width:0}
.cookie-bar-text strong{display:block;margin-bottom:2px;font-size:14px}
.cookie-bar-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
/* Both answers are the same button, on purpose: nothing here nudges. */
.cookie-bar .cookie-btn{min-width:124px;padding:9px 16px;border-radius:10px;cursor:pointer;
  border:1px solid rgba(128,140,160,.5);background:transparent;color:inherit;
  font:600 13.5px Inter,system-ui,sans-serif}
.cookie-bar .cookie-btn:hover{background:rgba(128,140,160,.16)}
.cookie-more{color:#6ea8ff;text-decoration:none;font-size:13px;white-space:nowrap}
body.light .cookie-more{color:#1f6feb}
.cookie-more:hover{text-decoration:underline}
@media (max-width:620px){
  .cookie-bar{left:8px;right:8px;bottom:8px;padding:14px;gap:12px}
  .cookie-bar-actions{width:100%}
  .cookie-bar .cookie-btn{flex:1 1 0;min-width:0}
}
</style>
CSS;

        return $css
             . '<div class="cookie-bar" id="cookieBar" role="dialog" aria-live="polite"'
             . ' aria-label="' . e($title) . '">'
             . '<div class="cookie-bar-text"><strong>' . e($title) . '</strong> ' . e($text) . '</div>'
             . '<div class="cookie-bar-actions">'
             // Reject first and identical in weight: the easy answer must not
             // be the one that suits the site.
             . '<button class="btn cookie-btn" type="button" data-consent="none">' . e($reject) . '</button>'
             . '<button class="btn cookie-btn" type="button" data-consent="all">' . e($accept) . '</button>'
             . '<a class="cookie-more" href="' . e($base) . 'cookies.php">' . e($more) . '</a>'
             . '</div>'
             . '</div>';
    }

    /** The small script that stores the answer. Same on every page. */
    public static function script(): string
    {
        $maxAge = self::YEAR;
        $name   = self::COOKIE;
        $ver    = self::VERSION;

        return <<<JS
<script>
(function(){
  var NAME='$name', VER=$ver;
  function remember(value){
    try{
      document.cookie=NAME+'='+value+':'+VER+';path=/;max-age=$maxAge;samesite=Lax'
        +(location.protocol==='https:'?';secure':'');
    }catch(e){}
  }
  document.addEventListener('click',function(e){
    var b=e.target.closest?e.target.closest('[data-consent]'):null;
    if(!b)return;
    e.preventDefault();
    var value=b.dataset.consent==='all'?'all':'none';
    remember(value);
    var bar=document.getElementById('cookieBar');
    if(bar)bar.remove();
    // Saying yes starts the measuring now, not on the next page: waiting for
    // a reload would lose the visit that just agreed to be counted.
    if(value==='all'&&window.H3D_GA_ID&&!window.H3D_GA_LOADED){
      window.H3D_GA_LOADED=true;
      var s=document.createElement('script');
      s.async=true;
      s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(window.H3D_GA_ID);
      document.head.appendChild(s);
      window.dataLayer=window.dataLayer||[];
      window.gtag=window.gtag||function(){dataLayer.push(arguments)};
      gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',
        ad_personalization:'denied',analytics_storage:'granted'});
      gtag('js',new Date());
      gtag('config',window.H3D_GA_ID,{anonymize_ip:true});
    }
    // The Cookies page shows the current answer; refresh it so the page does
    // not keep claiming the old one.
    if(b.dataset.consentReload)location.reload();
  });
})();
</script>

JS;
    }

    /**
     * Everything a page needs, in one call: the analytics tag when it is
     * allowed, the banner when it is not yet answered, and the script.
     */
    public static function render(string $base = ''): string
    {
        $out = self::analyticsTag();

        if (self::needed()) {
            // The id is exposed so "accept" can start analytics without a
            // reload. It is a public measurement id, not a secret.
            $out .= '<script>window.H3D_GA_ID=' . json_encode(self::analyticsId()) . ';'
                  . (self::accepted() ? 'window.H3D_GA_LOADED=true;' : '') . '</script>' . "\n";
            $out .= self::banner($base);
            $out .= self::script();
        }

        return $out;
    }
}

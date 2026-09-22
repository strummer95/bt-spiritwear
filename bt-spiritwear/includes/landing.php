<?php
/**
 * BT Spirit Wear — the landing page itself: form handler + [bt_spiritwear] markup.
 *
 * Ported from the Code Snippets snippet (v21). Behaviour is identical except:
 *   - functions are btsw_* so they can never collide with the old snippet
 *   - every $_POST read goes through wp_unslash(). WordPress adds backslashes to
 *     all POST data, so an apostrophe arrived in the lead email as We\'re or
 *     Child\'s Voice. Unslashing first fixes that for every field.
 *
 * History from the snippet days, still true:
 *   - Fields are namespaced sw_*. A field named "name" collided with WordPress's
 *     post-slug query var and 404'd every submit. btsw_strip_post_query_vars()
 *     is insurance against that whole class of bug.
 *   - POST is handled on init, before the main query, so page routing can never
 *     stop a lead from being emailed.
 *   - Success redirects to ?sw=thanks#get-started (the Meta custom conversion).
 *   - The redirect is sent and the connection closed BEFORE wp_mail(), so the
 *     visitor never waits on the SMTP handshake.
 */
if (!defined('ABSPATH')) exit;

/**
 * Belt and suspenders: during a bt_sw_form submit, drop any WordPress query
 * var that came in through $_POST only. Prevents a form field name from ever
 * hijacking the main query and 404ing the page again.
 */
function btsw_strip_post_query_vars($vars) {
    if (!isset($_POST['bt_sw_form'])) return $vars;
    foreach (array_keys($vars) as $k) {
        if (isset($_POST[$k]) && !isset($_GET[$k])) unset($vars[$k]);
    }
    return $vars;
}

/**
 * Runs on init — before WordPress parses the main query, so nothing about page
 * routing can stop the lead email. Sends the mail, then redirects to
 * ?sw=thanks#get-started for conversion tracking. On a validation error it
 * falls through and the page renders normally with the error + typed values.
 */
function btsw_handle_post() {

    if (is_admin()) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    if (!isset($_POST['bt_sw_form'])) return;

    /* ============ CONFIG ============ */
    $LEAD_EMAIL = btsw_lead_email();       // where lead notifications go
    /* ================================ */

    $path       = strtok($_SERVER['REQUEST_URI'], '?');
    $thanks_url = add_query_arg('sw', 'thanks', $path) . '#get-started';

    if (!empty($_POST['sw_website'])) {          // honeypot: fake success, send nothing
        wp_safe_redirect($thanks_url); exit;
    }

    $school = sanitize_text_field(wp_unslash($_POST['sw_school'] ?? ''));
    $name   = sanitize_text_field(wp_unslash($_POST['sw_name'] ?? ''));
    $role   = sanitize_text_field(wp_unslash($_POST['sw_role'] ?? ''));
    $email  = sanitize_email(wp_unslash($_POST['sw_email'] ?? ''));
    $phone  = sanitize_text_field(wp_unslash($_POST['sw_phone'] ?? ''));
    $timing = sanitize_text_field(wp_unslash($_POST['sw_timing'] ?? ''));
    $notes  = sanitize_textarea_field(wp_unslash($_POST['sw_notes'] ?? ''));

    if ($school === '' || $name === '' || !is_email($email)) {
        $GLOBALS['btsw_err'] = 'Please fill in your school, your name, and a valid email.';
        return;                                  // render the page with the error
    }

    $send_lead = function() use ($LEAD_EMAIL, $school, $name, $role, $email, $phone, $timing, $notes) {

        $FROM_ADDRESS = 'leads@' . preg_replace('/^www\./', '', parse_url(home_url(), PHP_URL_HOST));

        $body  = "New spirit wear store lead\n";
        $body .= "==========================\n\n";
        $body .= "School / Org: $school\n";
        $body .= "Name:         $name\n";
        $body .= "Role:         $role\n";
        $body .= "Email:        $email\n";
        $body .= "Phone:        $phone\n";
        $body .= "Timing:       $timing\n\n";
        $body .= "Notes:\n$notes\n";

        $headers = array(
            "From: Boomer T's Spirit Wear <{$FROM_ADDRESS}>",
            "Reply-To: {$name} <{$email}>",
        );

        wp_mail($LEAD_EMAIL, "Spirit Wear Store Lead — {$school}", $body, $headers);
    };

    /**
     * Send the redirect and CLOSE the connection before touching the mail
     * server. wp_mail() blocks for the whole SMTP handshake (2–4s), and the
     * visitor should never wait on that. The mail still sends in this same
     * request — no cron dependency, so a lead can't be lost.
     */
    wp_safe_redirect($thanks_url);                 // sets the Location header

    if (function_exists('fastcgi_finish_request')) {        // PHP-FPM (Bitnami default)
        while (ob_get_level()) { @ob_end_flush(); }
        @flush();
        fastcgi_finish_request();
        $send_lead();
        exit;
    }

    if (function_exists('litespeed_finish_request')) {      // LiteSpeed
        while (ob_get_level()) { @ob_end_flush(); }
        @flush();
        litespeed_finish_request();
        $send_lead();
        exit;
    }

    $send_lead();                                  // mod_php fallback: as before
    exit;
}

function btsw_render() {

    $sent = isset($_GET['sw']) && $_GET['sw'] === 'thanks';
    $err  = $GLOBALS['btsw_err'] ?? '';

    $BT_LOGO = // BT logo (base64 png), chunked to keep editor lines short
        'iVBORw0KGgoAAAANSUhEUgAAAFkAAABVCAMAAAAVF7jmAAAAkFBMVEX///////7///3//v7+///+//7+/v/+/v7+/vz9//79' .
        '/v75/v7//f///P/7/f37+v369vv87fvq4O31tdvFv82vmK1tTGR/LVwaJDw8HkUJHkMFHkQEHEPnCnXqBXDtAm3wAWvqAGXJ' .
        'D2rFB19RFj8RGC0QFCcJGjwKFzQEGkADFzoGEzQKEicCEjQFDicBBRozy78yAAAJc0lEQVR42pWZiXqyOhCGw2YAF/gpKNVq' .
        'q30QVJD7v7vzzYSw2/ZMi6DgyzCZzBKFEFJAbOylLSW9ldKXUhr0KYl6tW3pbRyxEG4YBoGLLQiDEMdhsNm4JELQV16I3ewt' .
        'sKdnXdOgG29CLcEGf81hEMzwpNAYKfufDq7BW8NVV5qk4cZVmrqmq45mVJGNtAeKL8X02gVZSy4WC7zwhQu2H+1nbUBQ0aCF' .
        'r8BSyvlLZcOS+jr1XTl/sT4lm7/XIl/ba2Li/iWytbuc2Hmq+M/8lty/srHz/AN2V8vfVZf9p5QNU86oIJuzsnUpOT/SjXay' .
        'L52xX4+g3zpTM/KzhmAV9CP2rpKv3GjgomL4LfnKfC9gon877Zwvh3foCn2vezXKYvI0vQf85Ss/OK6cvX7sUuOZ206qF/eS' .
        'IyfuRwAxc/du3GQ3CaUUP45bN4B+N7mneO0U8sdJ+srW8uVc6lF/jhRzgyd/1+OlcV9dz/8O0ozjeVJiG6YNux89fwo9c+J7' .
        '/hKy2SzVrhOfNs+jW8q/aquUNflgoZCvBHC/lzx+MW7zYC7tfP9n8pLInoNM+mczS6RtKQzp9zGeRyyPD7DzvI2HhChhbkv8' .
        'D4HuhinIkE2ZYHvti/7AcyyLHc/5P2RSY/HyDO0sshi4nj9Cy9ljqeP7ylI1jz2K91QAgdOg6DQUl7/OE9lNWcswhCOFzW8d' .
        'Z+i6wlr1AhmU8OgMLOMbhmHb06fscgfVGSvLWpGsIXiloxUdqc+afz4msvjdqy3LkZa9WK/Df3+TMFhxVAqiuJNoKjRrN4tF' .
        'EL39Wf6t6SEXQZKl769lmyRJjOuCKNnudpc/yC6J1isaojApn/XPkm7jcLGOkt1u9/277HZvwYoq3iC5ZlkxIznLPk3TrK63' .
        'UOTfW6v0GXI6HU+n8+ms5HI+Hs/YLpfvy1vIUYbIj/tYqvaI8GlWlltCJ9+Kev78PH+eTqdP7OnNqZMjyIHUOhe328eH3liw' .
        'Pxzo/+Owh+RFmr7HQEMj8AhGuM9WFBW7I5HDNTnVknS+HRhHvP2BRB188DEOAE+fKaF3QCvy1+cXhA6++ndQZLOvM6P3LI2J' .
        'G2E61K7fIwwjKc0W+NKioUp1ZQ2Q7SWTFfiepyPR5Ft+L9I6WZE9jiCfGnLPFMrKx8v37o28riHv2aT7Am5ybaUsn89rkSvy' .
        'vaiK63sUktKX7+/zqWfhkT8T2WjI9w82bV5v474kyXtdl2n+cfi4V9VDKZ0op75o9IkcrXVllreA4qmt7Ew630AehKVNGMVJ' .
        '9ixgESLnab0NQjXLE9hEedwJdp3MbleTc7bzHeSlME0VBxEqPd8TdpyWsPajIYcrDkzRGyn9RfOFRmwYkML1OrBa8o3Hr956' .
        'tupGQTYcX7qbQCR1mucPGIN1ximCv10U+VORm1hKYq3XkltatnNOM+OWk85M5m7VdA3p+r6I6weRKyaHhrTtFU2Zy1GTd2//' .
        'Vo5vSwNAW2x8w7XkpiUrdL2Fmi6g1DuarmkyGefzqqruTKZ8QuTd5Qin02TLoToDG+0AltrrcvKMG5NdV3cppkl98IbJN5iZ' .
        'yUtK4EKTW50tJPVmVaCtmRvyB6ELfNN1dHstuK8GuSxaaySCBkH2yEdlDTEs8jjZBgNrLNGe+zCT78MQLtVr4bbOFTnNshjt' .
        'M7LFlOz4qIyGNbgi7ym0Hcg3+Klah95som2NaXi7wzeybBv6lLSNiTWIPG7KFFlFNZDDniAJxtu0xPgpclkntm/7QvbIXydt' .
        'DVWNgGxTTdOzBk3C/SPdQnpZMLtmiEqw1J3m9jbyUcdKV/nGiSZKR54UXD3y4VaUZcmhiF4zCEU7nqD3oqjT2FOLJNrrzp2d' .
        'pesMyyxNZr+rKP89SgpyZcnJEHmMYzdF5xhFJ5dsVo+sdXb9ccU8ICPuVNWzerI8SComIzw/04jAMKdtWHNe54+bor6db/f7' .
        'o2I4SaUEaNL5kSURimSaBM6QfGFrMLnffdqoNzo731np6vHodK7YGocc5thGHvzckZ6ckpXOXSErtZ15DlK0YykKGj0UISln' .
        'QwreCNEZCgPbR2BwZOMbDfnCI+i3/aBuVJQ1OKcgi5YlkOQcjWScClWKTZ/Q2rYwhJ3Xjci9dSKts8rPj0y5cdpWeum1ztIm' .
        'hzMaM0U4U7LTWqMbxD4ZphwJkuG1hk/vbzS8QC8XaDGH1ti15GFQaskoVuqtWvGDNTl6LOg1fr+mGE74SF5k1xixzpkh++M+' .
        'TfbIFH8Rfk3DWqghQGZxpC+i9/rJ3ofS4Eqx35+Su067W6LsrEHZSiCRAAi+ya98ZXwtn+x+t5yUluugT+aZYmlytxLR0/kO' .
        'l7V5xVagb1E7vih6z8qqAhiT/JogxYZU3zH5pOPGzDoVyIXSmci+Ig+usoNtlimd7ynIziLQ5K8puftmS+bcjTxo6N4RtlCt' .
        'Zbi9Dsjr9YTs+NN2MkjKQmVYIqMosHrn0bi5ph++M5mGMC0TYXC2+qbZ/dVGJL2QJAc6I1shHHHuFg5SLOvMI4maA118jJle' .
        'qS4gJ7IM+mTyjbU1s5qq8yCUxkRAHty4HKkciKAQ5C2RCnW/odK3In925LHXSe3Pd1Xy75ns8eJCJ9H2me+b3uWR1Ym3kOsx' .
        'eW257mzkp8r8wOQ4HtW5qHSfBVJh0w49s8hz/QH5uyMPxtDW3QTsvC+yUcmfoVd8IiIdmIwBpHAn5e9kXSM9biwoWCgBlhTy' .
        'u5aQoiilG7p1eo2XiKN/I3M/mDOZmr/9jHCKvN+ov9qGtKYwsfPK6Mja/0Aus+KuwE3qqx6PZq+sq26L7ghR1qaJpMgnNVMa' .
        '8njt2oY1St3DPqoXcs/vaK0ILKjI0uRzFzfE9Kcassb1OQQN7oD7UbpFaCaww0X7ijtObr65SyPy2M62T7lbDdqjGTm9tUKl' .
        'TVmnScTlpmUa64b8fT42ZOkak4WiMKm50Hop1xqt2/U9iQI0ACohKJ1pWUD1fyF0npBtP7myF49duRNaPomjINy0K56rsFuQ' .
        'oP5vbSHYjOKo74ooSdRki+cFeTYMFqileyv763/4hm7/YlqDmV2Osl+vdeo6nd2192V4R0+CF4uVvJzmON2vl47nUFjyN7Qy' .
        'SWU2BdRhXpaWWlxTIrsfpYbr4FSoOxiBBaI88h86KtNxTPw5hkGfINMag9VtWuNzTfrND29IX0da8yv4bWhFnAcLQrnb5IYQ' .
        'ZLnA1v581vxUA/tIw2l/Xp2Q/wML5bajG097BAAAAABJRU5ErkJggg==';

    ob_start();
    ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
#btsw, #btsw *, #btsw *::before, #btsw *::after { box-sizing:border-box; margin:0; padding:0; }
body { overflow-x:clip; }
#btsw {
  /* full-bleed: breaks out of the theme's content column */
  width:100vw; position:relative; left:50%; margin-left:-50vw;
  --navy:#27267e; --navy-deep:#1b1a5e; --navy-soft:#3c3b9e;
  --pink:#e535ab; --pink-dark:#c41d90; --pink-soft:#fce7f5;
  --white:#fff; --gray-100:#f5f6fb; --gray-200:#e9eaf4; --gray-400:#a9abc7; --gray-600:#5b5e7a;
  font-family:'Poppins',sans-serif; font-size:17px; line-height:1.65;
  color:var(--navy); background:var(--white);
}
#btsw .wrap { max-width:1080px; margin:0 auto; padding:0 24px; }
#btsw a { color:inherit; }

/* ── type ── */
#btsw .eyebrow {
  font-weight:700; font-size:13px; letter-spacing:.2em; text-transform:uppercase; color:var(--pink);
  display:inline-flex; align-items:center; gap:10px;
}
#btsw .eyebrow::before { content:""; width:26px; height:4px; border-radius:2px; background:var(--pink); }
#btsw h1, #btsw h2 { font-weight:800; text-transform:uppercase; letter-spacing:.01em; line-height:1.12; color:var(--navy); }

/* ── reveals ── */
#btsw [data-reveal] { opacity:0; transform:translateY(48px); transition:opacity .85s cubic-bezier(.22,.9,.31,1), transform .85s cubic-bezier(.22,.9,.31,1); transition-delay:var(--d,0s); }
#btsw .model[data-reveal] { transform:translateX(-72px) translateY(20px); }
#btsw .model.alt[data-reveal] { transform:translateX(72px) translateY(20px); }
#btsw .stat[data-reveal] { transform:translateY(60px) scale(.9); }
#btsw form[data-reveal] { transform:translateY(60px) scale(.97); }
#btsw [data-reveal].in { opacity:1; transform:none; }
#btsw .model[data-reveal].in, #btsw .stat[data-reveal].in, #btsw form[data-reveal].in { transform:none; }
@media (prefers-reduced-motion:reduce){
  #btsw [data-reveal] { opacity:1; transform:none; transition:none; }
  #btsw * { animation-duration:.01ms !important; transition-duration:.01ms !important; }
}

/* ── buttons ── */
#btsw .cta {
  display:inline-block; background:var(--pink); color:#fff; text-decoration:none; border:none; cursor:pointer;
  font-family:'Poppins',sans-serif; font-size:15px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
  padding:16px 38px; border-radius:999px; transition:background .15s, transform .15s, box-shadow .15s;
  box-shadow:0 10px 26px rgba(229,53,171,.35);
}
#btsw .cta:hover { background:var(--pink-dark); transform:translateY(-2px); }
#btsw .cta:focus-visible, #btsw a:focus-visible, #btsw input:focus-visible, #btsw select:focus-visible, #btsw textarea:focus-visible, #btsw button:focus-visible { outline:3px solid var(--navy); outline-offset:2px; }
#btsw .ghost {
  display:inline-block; color:var(--navy); text-decoration:none;
  font-size:14px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; opacity:.7;
}
#btsw .ghost:hover { opacity:1; color:var(--pink); }

/* ── ribbon bands (navy sections with slanted pink edges, like the site header) ── */
#btsw .band { background:var(--navy); color:#fff; position:relative; }
#btsw .band::before, #btsw .band::after {
  content:""; position:absolute; left:-2%; right:-2%; height:16px; background:var(--pink); z-index:1;
}
#btsw .band::before { top:-7px; transform:skewY(-.8deg); }
#btsw .band::after  { bottom:-7px; transform:skewY(-.8deg); }

/* ── HERO (light, like the homepage) ── */
#btsw .hero { background:var(--white); padding:96px 0 100px; position:relative; overflow:hidden; }
#btsw .hero::before { /* soft pink wash upper-right */
  content:""; position:absolute; right:-220px; top:-220px; width:620px; height:620px; border-radius:50%;
  background:radial-gradient(circle, rgba(229,53,171,.12), transparent 65%); pointer-events:none;
}
#btsw .hero .wrap { position:relative; max-width:900px; text-align:center; }
#btsw .hero .eyebrow { justify-content:center; }
#btsw .hero .eyebrow::after { content:""; width:26px; height:4px; border-radius:2px; background:var(--pink); }
#btsw .hero h1 { font-size:clamp(38px,6.2vw,72px); margin:24px 0 8px; }
#btsw .hero h1 .alt { color:var(--pink); }
#btsw .hero p.sub { font-size:clamp(17px,2.2vw,20px); color:var(--gray-600); max-width:620px; margin:18px auto 38px; }
#btsw .hero .actions { display:flex; gap:26px; align-items:center; justify-content:center; flex-wrap:wrap; }
#btsw .hero .since { margin-top:52px; font-size:13px; font-weight:600; letter-spacing:.22em; text-transform:uppercase; color:var(--gray-400); }

/* ── STATEMENT (scroll-scrubbed strike-through) ── */
#btsw .statement { background:var(--white); padding:20px 0 110px; }
#btsw .statement .wrap { max-width:820px; text-align:center; }
#btsw .statement .line { font-weight:700; text-transform:uppercase; font-size:clamp(22px,3.6vw,40px); line-height:1.35; color:var(--navy); opacity:0; will-change:transform,opacity; transition:color .45s ease; }
#btsw .statement .k { position:relative; display:inline-block; }
#btsw .statement .k::after { content:""; position:absolute; left:-2%; width:104%; top:52%; height:5px; border-radius:3px; background:var(--pink); transform:scaleX(var(--k,0)); transform-origin:left center; transition:transform .15s linear; }
#btsw .statement .line.struck { color:var(--gray-400); }
#btsw .statement .line.punch { color:var(--navy); font-weight:800; margin-top:18px; }
#btsw .statement .line.punch em { font-style:normal; color:var(--pink); }
/* neon OPEN sign */
#btsw .neon-wrap {
  display:block; margin-top:40px;
  opacity:0; transform:translate(90px,90px) rotate(14deg) scale(.9);
  will-change:transform,opacity;
}
#btsw .neon {
  display:inline-flex; flex-direction:column; align-items:center; gap:6px;
  padding:26px 54px 24px; transform-origin:50% -46px;
  background:radial-gradient(120% 140% at 50% 0%, #232275 0%, var(--navy-deep) 55%, #0d0d38 100%);
  border:3px solid var(--pink); border-radius:22px;
  box-shadow:0 0 14px rgba(229,53,171,.55), inset 0 0 18px rgba(229,53,171,.35), 0 22px 50px rgba(27,26,94,.35);
}
#btsw .neon .neon-open {
  font-family:'Poppins',sans-serif; font-weight:800; font-size:clamp(38px,6vw,64px); line-height:1;
  letter-spacing:.22em; margin-right:-.22em; color:#fff;
  text-shadow:0 0 6px #fff, 0 0 14px var(--pink), 0 0 30px var(--pink), 0 0 52px var(--pink);
}
#btsw .neon .neon-sub {
  font-family:'Poppins',sans-serif; font-weight:700; font-size:clamp(14px,2vw,19px);
  letter-spacing:.34em; margin-right:-.34em; color:#ffd9f0;
  text-shadow:0 0 8px var(--pink), 0 0 18px var(--pink);
}
#btsw .neon-wrap.lit .neon { animation:btswFlicker 1.1s steps(1,end) 1, btswPulse 3.2s ease-in-out 1.1s infinite, btswSwing 4.6s ease-in-out infinite; }
@keyframes btswSwing {
  0%,100% { transform:rotate(-4deg); }
  50%     { transform:rotate(2.6deg); }
}
@keyframes btswFlicker {
  0%,14%,26%,39% { filter:brightness(.55); }
  8%,20%,32%,46%,100% { filter:brightness(1); }
}
@keyframes btswPulse {
  0%,100% { filter:brightness(1); }
  50% { filter:brightness(.9); }
}
@media (prefers-reduced-motion:reduce){
  #btsw .statement .line { opacity:1 !important; transform:none !important; color:var(--gray-400); }
  #btsw .statement .line.punch { color:var(--navy) !important; }
  #btsw .statement .k::after { transform:scaleX(1); }
  #btsw .neon-wrap { opacity:1 !important; transform:none !important; }
  #btsw .neon { animation:none !important; transform:none !important; }
}

/* ── PROCESS (pinned scroll story) ── */
#btsw .proc-sec { background:var(--gray-100); padding:90px 0 40px; }
#btsw .proc-head { text-align:center; max-width:700px; margin:0 auto 30px; padding:0 24px; }
#btsw .proc-head h2 { font-size:clamp(28px,4.2vw,46px); margin-top:18px; }
#btsw .proc { display:grid; grid-template-columns:1fr 1fr; gap:48px; max-width:1080px; margin:0 auto; padding:0 24px; }
#btsw .proc-sticky { position:sticky; top:110px; align-self:start; height:min(70vh,540px); display:flex; flex-direction:column; align-items:center; justify-content:center; gap:22px; }
#btsw .proc-art { width:min(100%,440px); background:#fff; border-radius:24px; box-shadow:0 24px 60px rgba(39,38,126,.14); border-top:5px solid var(--pink); position:relative; }
#btsw .proc-art svg { width:100%; height:auto; display:block; }
#btsw .proc-art .stage { opacity:0; transform:translateY(14px); transition:opacity .55s ease, transform .55s ease; }
#btsw .proc[data-stage="1"] .stage-1, #btsw .proc[data-stage="2"] .stage-2,
#btsw .proc[data-stage="3"] .stage-3, #btsw .proc[data-stage="4"] .stage-4 { opacity:1; transform:none; }
#btsw .proc-dots { display:flex; gap:10px; }
#btsw .proc-dots i { width:9px; height:9px; border-radius:50%; background:var(--gray-200); transition:all .3s; }
#btsw .proc-dots i.on { background:var(--pink); transform:scale(1.3); }
#btsw .pstep { min-height:72vh; display:flex; flex-direction:column; justify-content:center; padding:40px 0; }
#btsw .pstep .num { font-size:13px; font-weight:700; letter-spacing:.2em; text-transform:uppercase; color:var(--pink); margin-bottom:10px; }
#btsw .pstep h3 { font-size:clamp(22px,2.8vw,32px); font-weight:800; text-transform:uppercase; line-height:1.18; margin-bottom:14px; color:var(--navy); }
#btsw .pstep p { font-size:17px; color:var(--gray-600); max-width:420px; }
#btsw .pstep p b { color:var(--navy); }
@media (max-width:860px){
  #btsw .proc { grid-template-columns:1fr; gap:0; }
  #btsw .proc-sticky { top:84px; height:auto; padding:10px 0; z-index:5; background:var(--gray-100); }
  #btsw .proc-art { width:min(100%,300px); }
  #btsw .pstep { min-height:52vh; text-align:center; align-items:center; }
}

/* ── PROOF (navy ribbon band) ── */
#btsw .proof { padding:104px 0; }
#btsw .proof .wrap { display:grid; grid-template-columns:repeat(3,1fr); gap:40px; text-align:center; }
#btsw .stat .n { font-size:clamp(44px,6vw,72px); font-weight:800; line-height:1; color:#fff; font-family:'Poppins',sans-serif; }
#btsw .stat .n em { font-style:normal; color:var(--pink); }
#btsw .stat .l { font-size:13px; font-weight:600; letter-spacing:.16em; text-transform:uppercase; color:#b9b8e6; margin-top:12px; }
@media (max-width:720px){ #btsw .proof .wrap { grid-template-columns:1fr; gap:34px; } }

/* ── MODELS ── */
#btsw .models-sec { background:var(--white); padding:104px 0; }
#btsw .models-sec .head { text-align:center; max-width:640px; margin:0 auto 48px; }
#btsw .models-sec h2 { font-size:clamp(26px,3.8vw,42px); margin-top:18px; }
#btsw .models { display:grid; grid-template-columns:1fr 1fr; gap:26px; max-width:900px; margin:0 auto; padding:0 24px; }
#btsw .model { background:var(--gray-100); border-radius:24px; padding:36px 32px; border-top:5px solid var(--navy); }
#btsw .model.alt { border-top-color:var(--pink); }
#btsw .model .tag { display:inline-block; font-size:12px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:var(--navy); background:#fff; border-radius:999px; padding:6px 16px; box-shadow:0 2px 8px rgba(39,38,126,.1); }
#btsw .model.alt .tag { color:var(--pink); }
#btsw .model h3 { font-size:22px; font-weight:800; text-transform:uppercase; margin:16px 0 12px; color:var(--navy); }
#btsw .model p { font-size:15.5px; color:var(--gray-600); }
#btsw .model p + p { margin-top:10px; font-weight:700; color:var(--navy); }
@media (max-width:720px){ #btsw .models { grid-template-columns:1fr; } }

/* ── FORM (navy ribbon band) ── */
#btsw .form-sec { padding:104px 0 120px; }
#btsw .form-sec .head { text-align:center; max-width:640px; margin:0 auto 44px; }
#btsw .form-sec h2 { font-size:clamp(28px,4.2vw,46px); margin-top:18px; color:#fff; }
#btsw .form-sec .head p { color:#c9c8ef; margin-top:12px; font-size:18px; }
#btsw .form-sec .eyebrow { color:var(--pink); }
#btsw form, #btsw .success { background:#fff; color:var(--navy); border-radius:24px; padding:40px; max-width:620px; margin:0 auto; box-shadow:0 30px 80px rgba(0,0,0,.3); border-top:5px solid var(--pink); }
#btsw .frow { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media (max-width:600px){ #btsw .frow { grid-template-columns:1fr; } }
#btsw .field { margin-bottom:16px; }
#btsw label { display:block; font-weight:700; font-size:12px; letter-spacing:.1em; text-transform:uppercase; color:var(--gray-600); margin-bottom:6px; }
#btsw label .req { color:var(--pink); }
#btsw input:not([type=hidden]), #btsw select, #btsw textarea {
  width:100%; border:1.5px solid var(--gray-200); background:var(--gray-100); color:var(--navy);
  padding:13px 16px; font-family:'Poppins',sans-serif; font-size:15.5px; border-radius:14px;
  transition:border-color .15s, background .15s; -webkit-appearance:none; appearance:none;
}
#btsw input:focus, #btsw select:focus, #btsw textarea:focus { border-color:var(--pink); background:#fff; }
#btsw select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%235b5e7a' stroke-width='2' fill='none'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 16px center; }
#btsw textarea { min-height:84px; resize:vertical; }
#btsw .hp { position:absolute; left:-9999px; top:-9999px; }
#btsw .err { background:var(--pink-soft); border:1.5px solid var(--pink); color:#8c0c52; padding:12px 16px; border-radius:14px; font-weight:600; margin-bottom:18px; }
#btsw .success { text-align:center; }
#btsw .success .big { font-size:28px; font-weight:800; text-transform:uppercase; color:var(--navy); margin-bottom:10px; }
#btsw .success .big span { color:var(--pink); }

/* ── persistent CTAs: mobile bottom bar + desktop floating pill ── */
/* both are hidden until the visitor scrolls past the hero, and hide again at the form */
#btsw .sticky-cta { position:fixed; left:0; right:0; bottom:0; z-index:60; display:none; padding:10px 14px calc(10px + env(safe-area-inset-bottom)); background:rgba(255,255,255,.96); backdrop-filter:blur(6px); border-top:3px solid var(--pink); box-shadow:0 -8px 30px rgba(39,38,126,.15); transform:translateY(110%); transition:transform .32s cubic-bezier(.22,.9,.31,1); }
#btsw .sticky-cta .cta { display:block; text-align:center; padding:14px; box-shadow:none; }
@media (max-width:719px){
  #btsw .sticky-cta { display:block; }
  #btsw .sticky-cta.show { transform:none; }
  body { padding-bottom:72px; }
}

#btsw .float-cta { display:none; }
@media (min-width:720px){
  #btsw .float-cta {
    display:block; position:fixed; right:26px; bottom:26px; z-index:61;
    opacity:0; transform:translateY(22px) scale(.96); pointer-events:none;
    transition:opacity .3s ease, transform .3s cubic-bezier(.22,.9,.31,1);
  }
  #btsw .float-cta.show { opacity:1; transform:none; pointer-events:auto; }
  #btsw .float-cta .cta { padding:14px 30px; font-size:14px; box-shadow:0 12px 30px rgba(229,53,171,.45); }
  #btsw .float-cta .cta .arw { display:inline-block; margin-left:8px; transform:translateY(1px); }
}
@media (prefers-reduced-motion:reduce){
  #btsw .sticky-cta, #btsw .float-cta { transition:none; }
}

/* ── desktop-only spacing trim (mobile untouched) ── */
@media (min-width:860px){
  #btsw .hero { padding:72px 0 76px; }
  #btsw .hero .since { margin-top:38px; }
  #btsw .statement { padding:10px 0 84px; }
  #btsw .neon-wrap { margin-top:30px; }
  #btsw .proc-sec { padding:64px 0 24px; }
  #btsw .pstep { min-height:62vh; }
  #btsw .proof { padding:78px 0; }
  #btsw .models-sec { padding:76px 0; }
  #btsw .models-sec .head { margin-bottom:36px; }
  #btsw .form-sec { padding:76px 0 88px; }
  #btsw .form-sec .head { margin-bottom:34px; }
}
</style>

<div id="btsw">

  <!-- HERO -->
  <section class="hero">
    <div class="wrap">
      <span class="eyebrow" data-reveal>Spirit Wear Fundraiser Stores</span>
      <h1 data-reveal style="--d:.1s">We do the work.<br><span class="alt">You get the check.</span></h1>
      <p class="sub" data-reveal style="--d:.2s">A free online spirit wear store for your school — designed, printed, and shipped by Boomer T's. Your community shops one link. Your school earns on every order.</p>
      <div class="actions" data-reveal style="--d:.3s">
        <a class="cta" href="#get-started">Start Your Free Store</a>
        <a class="ghost" href="#how">See how it works ↓</a>
      </div>
      <p class="since" data-reveal style="--d:.45s">Printing for schools since 1984 &nbsp;•&nbsp; Oswego, Illinois</p>
    </div>
  </section>

  <!-- STATEMENT -->
  <section class="statement">
    <div class="wrap" id="btswKill">
      <p class="line" style="--kd:0s"><span class="k">Order windows.</span></p>
      <p class="line" style="--kd:.45s"><span class="k">Batch printing.</span></p>
      <p class="line" style="--kd:.9s"><span class="k">Five-week waits.</span></p>
      <p class="line punch">Your store never closes. <em>Orders print as they come in — and ship straight to their door.</em></p>
      <div class="neon-wrap" id="btswNeon" aria-hidden="true">
        <div class="neon">
          <span class="neon-open">OPEN</span>
          <span class="neon-sub">24 / 7 / 365</span>
        </div>
      </div>
    </div>
  </section>

  <!-- PROCESS -->
  <section class="proc-sec" id="how">
    <div class="proc-head">
      <span class="eyebrow" data-reveal>How It Works</span>
      <h2 data-reveal style="--d:.1s">You Share A Link.<br>We Do The Rest.</h2>
    </div>
    <div class="proc" id="btswProc" data-stage="1">
      <div class="proc-sticky">
        <div class="proc-art">
          <svg viewBox="0 0 440 400" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="How your spirit wear store comes together">
            <!-- STAGE 1 : we create your store -->
            <g class="stage stage-1">
              <rect x="70" y="60" width="300" height="260" rx="14" fill="#f5f6fb" stroke="#27267e" stroke-width="2.5"/>
              <rect x="70" y="60" width="300" height="44" rx="14" fill="#1b1a5e"/>
              <rect x="70" y="90" width="300" height="14" fill="#1b1a5e"/>
              <circle cx="92" cy="82" r="5" fill="#e535ab"/>
              <text x="112" y="88" font-family="Poppins,sans-serif" font-weight="600" font-size="15" fill="#fff" letter-spacing="1">YOUR SCHOOL STORE</text>
              <g stroke="#27267e" stroke-width="2" fill="#fff">
                <rect x="92" y="124" width="80" height="80" rx="8"/>
                <rect x="180" y="124" width="80" height="80" rx="8"/>
                <rect x="268" y="124" width="80" height="80" rx="8"/>
              </g>
              <path d="M116 190 l8-34 8 6 8-6 8 34 z M204 190 l8-34 8 6 8-6 8 34 z M292 190 l8-34 8 6 8-6 8 34 z" fill="#e9eaf4" stroke="#27267e" stroke-width="1.6"/>
              <rect x="92" y="220" width="256" height="12" rx="6" fill="#e9eaf4"/>
              <rect x="92" y="242" width="180" height="12" rx="6" fill="#e9eaf4"/>
              <rect x="92" y="272" width="120" height="30" rx="8" fill="#e535ab"/>
              <text x="152" y="292" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="600" font-size="13" fill="#fff" letter-spacing="1.5">ADD TO CART</text>
            </g>
            <!-- STAGE 2 : share one link -->
            <g class="stage stage-2">
              <circle cx="220" cy="190" r="110" fill="none" stroke="#e9eaf4" stroke-width="2"/>
              <circle cx="220" cy="190" r="150" fill="none" stroke="#e9eaf4" stroke-width="2"/>
              <rect x="90" y="168" width="260" height="46" rx="23" fill="#fff" stroke="#27267e" stroke-width="2.5"/>
              <circle cx="118" cy="191" r="9" fill="none" stroke="#e535ab" stroke-width="3"/>
              <path d="M124 185 l8-8 M112 197 l-8 8" stroke="#e535ab" stroke-width="3" stroke-linecap="round"/>
              <text x="142" y="197" font-family="Poppins,sans-serif" font-weight="600" font-size="16" fill="#27267e">boomerts.com/yourschool</text>
              <text x="220" y="90" text-anchor="middle" font-family="Poppins,sans-serif" font-size="15" fill="#5b5e7a" letter-spacing="3">NEWSLETTER • FACEBOOK • GAME PROGRAM</text>
              <text x="220" y="315" text-anchor="middle" font-family="Poppins,sans-serif" font-size="15" fill="#5b5e7a" letter-spacing="3">PARENTS ORDER ANY DEVICE, ANY TIME</text>
            </g>
            <!-- STAGE 3 : we handle the rest -->
            <g class="stage stage-3">
              <path d="M190 96 L150 104 l-38 22 18 30 26-12 v156 h128 v-156 l26 12 18-30 -38-22 L250 96 a30 18 0 0 1 -60 0 z" fill="#fff" stroke="#27267e" stroke-width="3" stroke-linejoin="round"/>
              <path d="M246 99 a26 15 0 0 1 -52 0" fill="none" stroke="#27267e" stroke-width="2" opacity=".45"/>
              <path d="M168 290 h104" stroke="#27267e" stroke-width="2" opacity=".25"/>
              <image href="data:image/png;base64,<?php echo $BT_LOGO; ?>" x="168" y="136" width="104" height="84" preserveAspectRatio="xMidYMid meet"/>
              <text x="220" y="345" text-anchor="middle" font-family="Poppins,sans-serif" font-size="15" fill="#5b5e7a" letter-spacing="3">PRINTING • EMBROIDERY</text>
            </g>
            <!-- STAGE 4 : you get the check -->
            <g class="stage stage-4">
              <rect x="70" y="120" width="300" height="150" rx="12" fill="#fff" stroke="#27267e" stroke-width="3"/>
              <text x="92" y="158" font-family="Poppins,sans-serif" font-size="15" fill="#5b5e7a" letter-spacing="2">PAY TO THE ORDER OF</text>
              <text x="92" y="192" font-family="Poppins,sans-serif" font-weight="600" font-size="24" fill="#27267e" letter-spacing="1">YOUR SCHOOL</text>
              <line x1="92" y1="238" x2="240" y2="238" stroke="#a9abc7" stroke-width="2"/>
              <text x="330" y="196" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="700" font-size="34" fill="#e535ab">$</text>
              <g transform="rotate(-12 300 132)">
                <rect x="252" y="106" width="96" height="40" rx="8" fill="none" stroke="#e535ab" stroke-width="3"/>
                <text x="300" y="132" text-anchor="middle" font-family="Poppins,sans-serif" font-weight="700" font-size="15" fill="#e535ab" letter-spacing="2">EARNED</text>
              </g>
              <text x="220" y="320" text-anchor="middle" font-family="Poppins,sans-serif" font-size="15" fill="#5b5e7a" letter-spacing="3">EVERY ORDER PAYS YOUR PROGRAM</text>
            </g>
          </svg>
        </div>
        <div class="proc-dots" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i></div>
      </div>

      <div class="proc-steps">
        <div class="pstep" data-stage="1">
          <div class="num">STEP 01</div>
          <h3>We create your store</h3>
          <p>Send us a logo — or just an idea — and our artists build your designs. Your colors, your gear: tees, hoodies, hats, names &amp; numbers. <b>You approve everything before it goes live.</b></p>
        </div>
        <div class="pstep" data-stage="2">
          <div class="num">STEP 02</div>
          <h3>You share one link</h3>
          <p>Drop it in the newsletter, the Facebook group, the game program. Parents shop on their phone — <b>no forms, no cash, no size counts.</b></p>
        </div>
        <div class="pstep" data-stage="3">
          <div class="num">STEP 03</div>
          <h3>We handle the rest</h3>
          <p>Orders come straight to us. We print, pack, and ship every one in <b>3–5 business days</b> — screen print, embroidery, and DTF, all in-house in Oswego. Straight to their door, nothing for the school to sort.</p>
        </div>
        <div class="pstep" data-stage="4">
          <div class="num">STEP 04</div>
          <h3>Your school gets the check</h3>
          <p>You set the fundraising amount on every item — add a little, a lot, or keep prices low. Whatever you add on comes back to your school as a check. <b>We do the work. You get the check.</b></p>
        </div>
      </div>
    </div>
  </section>

  <!-- PROOF -->
  <section class="proof band">
    <div class="wrap">
      <div class="stat" data-reveal>
        <div class="n"><span data-count="40">40</span><em>+</em></div>
        <div class="l">Years printing • Since 1984</div>
      </div>
      <div class="stat" data-reveal style="--d:.15s">
        <div class="n">3<em>–</em>5</div>
        <div class="l">Business days from order to shipped</div>
      </div>
      <div class="stat" data-reveal style="--d:.3s">
        <div class="n"><em>$</em>0</div>
        <div class="l">Cost to your school. Ever.</div>
      </div>
    </div>
  </section>

  <!-- MODELS -->
  <section class="models-sec">
    <div class="head">
      <span class="eyebrow" data-reveal>Two Ways To Run It</span>
      <h2 data-reveal style="--d:.1s">Spirit Wear Store or Team Order</h2>
    </div>
    <div class="models">
      <div class="model" data-reveal>
        <span class="tag">Always Open</span>
        <h3>The Spirit Wear Store</h3>
        <p>Always open, all school year. Orders print as they come in and ship straight to each family in 3–5 business days.</p>
        <p>Best for PTOs, boosters &amp; all-school spirit wear.</p>
      </div>
      <div class="model alt" data-reveal style="--d:.15s">
        <span class="tag">Delivered Together</span>
        <h3>Team &amp; Event Orders</h3>
        <p>Need matching gear delivered together — a team, a club, field day? Set a close date and we'll print the whole run and deliver it sorted and labeled.</p>
        <p>Best for sports teams, clubs &amp; staff orders.</p>
      </div>
    </div>
  </section>

  <!-- FORM -->
  <section class="form-sec band" id="get-started">
    <div class="head">
      <span class="eyebrow" data-reveal>Get Started</span>
      <h2 data-reveal style="--d:.1s">Start Your Free Store</h2>
      <p data-reveal style="--d:.2s">Takes two minutes — your school or team can get a store going right away.</p>
    </div>

    <div class="wrap">
    <?php if ($sent): ?>
      <div class="success">
        <p class="big">You're In<span>.</span></p>
        <p>Thanks — we got it. A real person from our shop will reach out within one business day to get your store rolling.</p>
      </div>
    <?php else: ?>
      <?php if ($err): ?><div class="err" style="max-width:620px;margin:0 auto 18px"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
      <form method="post" action="<?php echo esc_url(strtok($_SERVER['REQUEST_URI'], '?')); ?>#get-started" novalidate data-reveal style="--d:.25s">
        <input type="hidden" name="bt_sw_form" value="1">
        <div class="hp" aria-hidden="true">
          <label for="website">Website</label>
          <input type="text" id="website" name="sw_website" tabindex="-1" autocomplete="off">
        </div>
        <div class="field">
          <label for="school">School / Organization <span class="req">*</span></label>
          <input type="text" id="school" name="sw_school" required value="<?php echo btsw_old('sw_school'); ?>" placeholder="e.g. Prairie Point Elementary PTO">
        </div>
        <div class="frow">
          <div class="field">
            <label for="name">Your Name <span class="req">*</span></label>
            <input type="text" id="name" name="sw_name" required value="<?php echo btsw_old('sw_name'); ?>">
          </div>
          <div class="field">
            <label for="role">Your Role</label>
            <select id="role" name="sw_role">
              <option value="">Choose one…</option>
              <option>PTO / Booster Club</option>
              <option>Athletic Director</option>
              <option>Coach</option>
              <option>Teacher / Staff</option>
              <option>Administrator</option>
              <option>Parent Volunteer</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="frow">
          <div class="field">
            <label for="email">Email <span class="req">*</span></label>
            <input type="email" id="email" name="sw_email" required value="<?php echo btsw_old('sw_email'); ?>">
          </div>
          <div class="field">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="sw_phone" value="<?php echo btsw_old('sw_phone'); ?>">
          </div>
        </div>
        <div class="field">
          <label for="timing">Where are you in the process?</label>
          <select id="timing" name="sw_timing">
            <option value="">Choose one…</option>
            <option>We're ready to get started!</option>
            <option>Ready in the next month or two</option>
            <option>Planning ahead for next season</option>
            <option>Just gathering information</option>
          </select>
        </div>
        <div class="field">
          <label for="notes">Anything else we should know?</label>
          <textarea id="notes" name="sw_notes" placeholder="Mascot, colors, team names, fundraising goals…"><?php echo btsw_old('sw_notes'); ?></textarea>
        </div>
        <button class="cta" type="submit" style="width:100%">Start My Store</button>
      </form>
    <?php endif; ?>
    </div>
  </section>

  <div class="sticky-cta" id="btswSticky">
    <a class="cta" href="#get-started">Start Your Free Store</a>
  </div>

  <div class="float-cta" id="btswFloat">
    <a class="cta" href="#get-started">Get Started Now<span class="arw" aria-hidden="true">↓</span></a>
  </div>

</div><!-- /#btsw -->

<script>
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* reveals */
  var revealed = document.querySelectorAll('#btsw [data-reveal]');
  if ('IntersectionObserver' in window && !reduce) {
    var ro = new IntersectionObserver(function(es){
      es.forEach(function(e){ if (e.isIntersecting) { e.target.classList.add('in'); ro.unobserve(e.target); } });
    }, {threshold:.15, rootMargin:'0px 0px -10% 0px'});
    revealed.forEach(function(el){ ro.observe(el); });
  } else {
    revealed.forEach(function(el){ el.classList.add('in'); });
  }

  /* statement: scroll-scrubbed strikes (ratcheted) + parallax drift */
  var kWrap = document.getElementById('btswKill');
  if (kWrap) {
    var kLines = kWrap.querySelectorAll('.line');
    if (reduce) {
      kLines.forEach(function(el,i){ if (i < 3) el.classList.add('struck'); var k = el.querySelector('.k'); if (k) k.style.setProperty('--k', 1); });
    } else {
      var kMax = 0, kTick = false;
      var kCfg = [ /* [fadeStart, fadeEnd, strikeStart, strikeEnd] */
        [0.00, 0.14, 0.10, 0.30],
        [0.16, 0.30, 0.28, 0.48],
        [0.32, 0.46, 0.46, 0.66],
        [0.70, 0.90, 0, 0]
      ];
      var kDrift = [46, 32, 18, -8];
      var kEase = function(t){ return t <= 0 ? 0 : t >= 1 ? 1 : t*t*(3-2*t); };
      var kSub  = function(p,a,b){ return Math.min(1, Math.max(0, (p-a)/(b-a))); };
      var kFrame = function(){
        var r = kWrap.getBoundingClientRect(), vh = window.innerHeight;
        var raw = Math.min(1, Math.max(0, (vh*0.9 - r.top) / (r.height + vh*0.5)));
        if (raw > kMax) kMax = raw;           // ratchet: never un-strike
        kLines.forEach(function(el,i){
          var c = kCfg[i];
          var f = kEase(kSub(kMax, c[0], c[1]));
          var drift = (0.5 - raw) * kDrift[i]; // parallax: moves both directions
          el.style.opacity = f;
          el.style.transform = 'translateY(' + (((1-f)*22) + drift).toFixed(2) + 'px)';
          var k = el.querySelector('.k');
          if (k) {
            var s = kEase(kSub(kMax, c[2], c[3]));
            k.style.setProperty('--k', s);
            if (s > 0.55) el.classList.add('struck');
          }
        });
        var neon = document.getElementById('btswNeon');
        if (neon) {
          var np = kEase(kSub(kMax, 0.50, 0.80));            // slides in right after the strikes, mid-viewport
          var nd = (0.5 - raw) * -16;                        // its own counter-drift
          neon.style.opacity = np;
          neon.style.transform = 'translate(' + ((1-np)*90).toFixed(1) + 'px,' + (((1-np)*90) + nd).toFixed(1) + 'px) '
                               + 'rotate(' + ((1-np)*14).toFixed(1) + 'deg) scale(' + (0.9 + np*0.1).toFixed(3) + ')';
          if (np >= 0.98) neon.classList.add('lit');         // flicker on, then swing + pulse forever
        }
        kTick = false;
      };
      var kOnScroll = function(){ if (!kTick) { kTick = true; requestAnimationFrame(kFrame); } };
      window.addEventListener('scroll', kOnScroll, {passive:true});
      window.addEventListener('resize', kOnScroll, {passive:true});
      kFrame();
    }
  }

  /* pinned process stages */
  var proc = document.getElementById('btswProc');
  if (proc) {
    var dots = proc.querySelectorAll('.proc-dots i');
    var setStage = function(n){
      proc.dataset.stage = n;
      dots.forEach(function(d,i){ d.classList.toggle('on', i < n); });
    };
    if ('IntersectionObserver' in window) {
      var po = new IntersectionObserver(function(es){
        es.forEach(function(e){ if (e.isIntersecting) setStage(e.target.dataset.stage); });
      }, {rootMargin:'-45% 0px -45% 0px'});
      proc.querySelectorAll('.pstep').forEach(function(s){ po.observe(s); });
    } else { setStage(5); }
  }

  /* stat count-up */
  if ('IntersectionObserver' in window && !reduce) {
    var co = new IntersectionObserver(function(es){
      es.forEach(function(e){
        if (!e.isIntersecting) return;
        var el = e.target, end = parseInt(el.dataset.count,10), t0 = null;
        var tick = function(ts){
          if (!t0) t0 = ts;
          var p = Math.min((ts - t0)/900, 1);
          el.textContent = Math.round(end * (1 - Math.pow(1-p,3)));
          if (p < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        co.unobserve(el);
      });
    }, {threshold:.6});
    document.querySelectorAll('#btsw [data-count]').forEach(function(el){ co.observe(el); });
  }

  /* persistent CTAs: show once past the hero, hide again once the form is on screen */
  var sticky  = document.getElementById('btswSticky');
  var floater = document.getElementById('btswFloat');
  var gs      = document.getElementById('get-started');
  var hero    = document.querySelector('#btsw .hero');
  if ((sticky || floater) && gs) {
    var pastHero = false, atForm = false;
    var applyCta = function(){
      var show = pastHero && !atForm;
      if (sticky)  sticky.classList.toggle('show', show);
      if (floater) floater.classList.toggle('show', show);
    };
    if ('IntersectionObserver' in window) {
      if (hero) {
        new IntersectionObserver(function(es){
          pastHero = !es[0].isIntersecting; applyCta();
        }, {threshold:0}).observe(hero);
      } else { pastHero = true; }
      new IntersectionObserver(function(es){
        atForm = es[0].isIntersecting; applyCta();
      }, {threshold:.1}).observe(gs);
      applyCta();
    } else {
      pastHero = true; applyCta();
    }
  }

  /* every CTA jumps smoothly to the form (theme has no scroll-behavior of its own) */
  document.querySelectorAll('#btsw a[href="#get-started"]').forEach(function(a){
    a.addEventListener('click', function(ev){
      var t = document.getElementById('get-started');
      if (!t) return;
      ev.preventDefault();
      var y = t.getBoundingClientRect().top + window.pageYOffset - 12;
      window.scrollTo({ top:y, behavior: reduce ? 'auto' : 'smooth' });
      if (history.replaceState) history.replaceState(null, '', '#get-started');
      if (window.matchMedia('(min-width:720px)').matches) {   // no keyboard pop on phones
        var first = document.getElementById('school');
        if (first) setTimeout(function(){ try { first.focus({preventScroll:true}); } catch(e) { first.focus(); } }, reduce ? 0 : 650);
      }
    });
  });

  <?php if ($sent): ?>
  if (window.fbq) fbq('track', 'Lead'); // fires against the site's existing Meta Pixel
  <?php endif; ?>
})();
</script>
    <?php
    return ob_get_clean();
}

function btsw_old($k) { return htmlspecialchars(wp_unslash($_POST[$k] ?? ''), ENT_QUOTES); }

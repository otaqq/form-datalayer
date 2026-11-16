# Output

What goes into the page. v0.1 writes for Google Tag Manager; how the pushes are written is kept in one place so that other tools (Matomo Tag Manager's `_mtm`, `gtag('event', …)`) can be added later.

## Settings of the app

These come from the app's configuration (environment variables), not from the settings file:

| Setting | Meaning |
|---|---|
| container id | The GTM container of the page, `GTM-` and upper-case letters or digits. The app may use different containers for different parts (the public site and the admin screen) |
| variable | The name of the data layer, `dataLayer` unless set. An ASCII JavaScript identifier |
| nonce | The CSP nonce of the page, when the app uses one. Added to every `<script>` |

An empty container id writes nothing at all: no tag and no pushes. A container id or variable that breaks the rules also writes nothing, and logs an error.

## In `<head>`

As early as possible, the pushes first, then the GTM snippet:

```html
<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({"event":"front_entry_confirm","form":"front.entry","step":"confirm"});</script>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-XXXX');</script>
```

- The push script is left out when there is nothing to push. Several pushes go into one script, in order.
- The snippet is Google's, with the variable and the container id filled in.

## After `<body>`

```html
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXX" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
```

## Writing values into a script

Each push is written as compact JSON (no spaces), with these characters escaped as `\uXXXX` (upper-case hex), so that no value can end the script or the attribute it is in: `<`, `>`, `&`, `'`, `"` inside strings, U+2028 and U+2029. Every other character, `/` and non-ASCII ones included, is written as it is. The variable and the container id are checked against their rules, never escaped into shape.

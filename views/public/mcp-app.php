<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Public products and information</title>
<style>
:root{color-scheme:light dark;--bapx-ink:var(--color-text-primary,#222);--bapx-paper:var(--color-background-primary,#faf7f0)}
*{box-sizing:border-box}body{margin:0;padding:16px;background:var(--bapx-paper);color:var(--bapx-ink);font:14px/1.6 var(--font-sans,system-ui,sans-serif);overflow-wrap:anywhere}
#mcp-status{margin:0 0 8px}#mcp-status:empty{display:none}#mcp-fallback{white-space:pre-wrap;font:inherit;margin:12px 0 0;max-height:320px;overflow:auto}
[hidden]{display:none!important}a:focus-visible{outline:3px solid #a67a64;outline-offset:3px}
<?= $styles ?>
</style>
</head>
<body>
<p id="mcp-status" role="status" aria-live="polite">Opening public information…</p>
<section id="mcp-cards" aria-label="Published website information"></section>
<pre id="mcp-fallback" hidden></pre>
<noscript>This view needs JavaScript. The chat answer includes the same public information.</noscript>
<script id="bapx-mcp-config" type="application/json"><?= json_encode(['publicBaseUrl'=>$publicOrigin],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
<script><?= $renderer ?></script>
<script><?= $bridge ?></script>
</body>
</html>

<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') · Panel zamówień</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --ink:#1f2328; --muted:#636c76; --line:#d8dee4; --accent:#1f6feb; --danger:#cf222e; --ok:#1a7f37; }
        * { box-sizing: border-box; }
        [hidden] { display:none !important; }
        body { margin:0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--bg); color:var(--ink); }
        header { background:#24292f; color:#fff; padding:10px 20px; display:flex; gap:20px; align-items:center; flex-wrap:wrap; }
        header a { color:#fff; text-decoration:none; }
        header nav { display:flex; gap:16px; flex:1; flex-wrap:wrap; }
        header form { margin:0; }
        main { max-width:1100px; margin:24px auto; padding:0 16px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:8px; padding:20px; margin-bottom:16px; }
        table { width:100%; border-collapse:collapse; background:var(--card); }
        th, td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--line); vertical-align:top; }
        th { font-size:13px; color:var(--muted); font-weight:600; }
        .btn { display:inline-block; padding:6px 14px; border-radius:6px; border:1px solid var(--line); background:#f6f8fa; color:var(--ink); text-decoration:none; cursor:pointer; font:inherit; }
        .btn-primary { background:var(--accent); border-color:var(--accent); color:#fff; }
        .btn-danger { color:var(--danger); }
        .btn-link { background:none; border:none; color:#fff; cursor:pointer; font:inherit; padding:0; }
        label { display:block; font-weight:600; margin:12px 0 4px; }
        input[type=text], input[type=email], input[type=password], input[type=number], input[type=date], select { width:100%; max-width:420px; padding:7px 10px; border:1px solid var(--line); border-radius:6px; font:inherit; }
        .error { color:var(--danger); font-size:13px; margin-top:4px; }
        .flash { background:#dafbe1; border:1px solid #aceebb; padding:10px 14px; border-radius:6px; margin-bottom:16px; }
        .muted { color:var(--muted); }
        .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; background:#eaeef2; }
        .badge-warn { background:#fff8c5; }
        .badge-danger { background:#ffebe9; color:var(--danger); }
        .badge-ok { background:#dafbe1; }
        .issue { border-left:4px solid var(--line); padding:8px 12px; margin:8px 0; background:var(--card); }
        .issue-krytyczna { border-left-color:var(--danger); }
        .issue-ostrzezenie { border-left-color:#d4a72c; }
        .issue form { margin:6px 0 0; display:flex; gap:6px; flex-wrap:wrap; }
        .issue input[type=text] { max-width:360px; }
        .filters { display:flex; gap:8px; flex-wrap:wrap; align-items:end; margin-bottom:16px; }
        .filters input, .filters select { width:auto; min-width:180px; }
        .table-wrap { overflow-x:auto; }
        .pagination { display:flex; gap:12px; margin:16px 0; }
        dl.details { display:grid; grid-template-columns:max-content 1fr; gap:6px 20px; margin:0; }
        dl.details dt { color:var(--muted); }
        dl.details dd { margin:0; }
        .row-actions { display:flex; gap:8px; }
        .row-actions form { margin:0; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px; }
        .stat strong { display:block; font-size:28px; }
    </style>
</head>
<body>
@auth
<header>
    <strong><a href="{{ route('dashboard') }}">Panel zamówień</a></strong>
    <nav>
        <a href="{{ route('dashboard') }}">Pulpit</a>
        <a href="{{ route('discrepancies.index') }}">Do wyjaśnienia <span id="nav-discrepancies" class="badge badge-danger" hidden></span></a>
        <a href="{{ route('orders.index') }}">Zamówienia</a>
        <a href="{{ route('cancellations.index') }}">Anulacje</a>
        <a href="{{ route('returns.index') }}">Zwroty</a>
        <a href="{{ route('complaints.index') }}">Reklamacje</a>
        <a href="{{ route('companies.index') }}">Spółki</a>
        <a href="{{ route('channels.index') }}">Kanały sprzedaży</a>
        <a href="{{ route('statuses.index') }}">Statusy</a>
    </nav>
    <button id="enable-notifications" class="btn-link" type="button" hidden>Włącz powiadomienia</button>
    <span class="muted" style="color:#c9d1d9">{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn-link" type="submit">Wyloguj</button></form>
</header>
@endauth
<main>
    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @yield('content')
</main>
@auth
<script>
// Co minutę sprawdzamy rozbieżności. Licznik w menu i w tytule karty działa zawsze,
// powiadomienia systemowe tylko po zgodzie użytkownika i przez HTTPS albo localhost.
(function () {
    var stateUrl = @json(route('notifications.state', absolute: false));
    var baseTitle = document.title;
    var badge = document.getElementById('nav-discrepancies');
    var button = document.getElementById('enable-notifications');
    var storageKey = 'panel-powiadomienia-widziane';
    var canNotify = 'Notification' in window && window.isSecureContext;

    function seen() {
        try { return JSON.parse(localStorage.getItem(storageKey)) || null; } catch (e) { return null; }
    }
    function remember(keys) {
        try { localStorage.setItem(storageKey, JSON.stringify(keys)); } catch (e) {}
    }
    function notify(item) {
        if (!canNotify || Notification.permission !== 'granted') return;
        var n = new Notification(item.title, { body: item.message, tag: item.key });
        n.onclick = function () { window.focus(); window.location.href = item.url; };
    }
    function refreshButton() {
        button.hidden = !(canNotify && Notification.permission === 'default');
    }

    function check() {
        fetch(stateUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function (state) {
                var critical = state.critical.length;
                badge.textContent = state.total;
                badge.hidden = state.total === 0;
                document.title = (critical ? '(' + critical + ') ' : '') + baseTitle;

                var items = state.critical.slice();
                if (state.sync_error) items.push(state.sync_error);
                var keys = items.map(function (i) { return i.key; });
                var previous = seen();

                // Przy pierwszym uruchomieniu tylko zapamiętujemy stan, żeby nie zasypać użytkownika.
                if (previous !== null) {
                    items.filter(function (i) { return previous.indexOf(i.key) === -1; }).slice(0, 5).forEach(notify);
                }
                remember(keys);
            })
            .catch(function () {});
    }

    button.addEventListener('click', function () {
        Notification.requestPermission().then(refreshButton);
    });

    refreshButton();
    check();
    setInterval(check, 60000);
})();
</script>
@endauth
</body>
</html>

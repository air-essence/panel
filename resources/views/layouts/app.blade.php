<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') · Panel zamówień</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --ink:#1f2328; --muted:#636c76; --line:#d8dee4; --accent:#1f6feb; --danger:#cf222e; --ok:#1a7f37; }
        * { box-sizing: border-box; }
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
        input[type=text], input[type=email], input[type=password], input[type=number], select { width:100%; max-width:420px; padding:7px 10px; border:1px solid var(--line); border-radius:6px; font:inherit; }
        .error { color:var(--danger); font-size:13px; margin-top:4px; }
        .flash { background:#dafbe1; border:1px solid #aceebb; padding:10px 14px; border-radius:6px; margin-bottom:16px; }
        .muted { color:var(--muted); }
        .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; background:#eaeef2; }
        .badge-warn { background:#fff8c5; }
        .badge-danger { background:#ffebe9; color:var(--danger); }
        .badge-ok { background:#dafbe1; }
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
        <a href="{{ route('orders.index') }}">Zamówienia</a>
        <a href="{{ route('companies.index') }}">Spółki</a>
        <a href="{{ route('channels.index') }}">Kanały sprzedaży</a>
        <a href="{{ route('statuses.index') }}">Statusy</a>
    </nav>
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
</body>
</html>

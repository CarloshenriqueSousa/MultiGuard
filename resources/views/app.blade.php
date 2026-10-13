{{--
    View raiz do console. O Inertia injeta aqui a pagina React escolhida pela rota. O @vite carrega o
    app.jsx e o componente da pagina atual, e o @viteReactRefresh liga o recarregamento instantaneo do React.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Multi-Guard</title>
    @viteReactRefresh
@vite('resources/js/app.jsx')
    @inertiaHead
</head>
<body class="min-h-screen antialiased">
    @inertia
</body>
</html>

@php
    $pageDir = str_replace('/', '.', $pageDir);
    $cssView = $pageDir . '.css';
    $mainView = $pageDir . '.main';
    $jsView = $pageDir . '.js';
@endphp

@if(view()->exists($cssView))
    <style>
        @include($cssView)
    </style>
@endif

@if(view()->exists($mainView))
    @include($mainView)
@endif

@if(view()->exists($jsView))
    <script>
        (function() {
            @include($jsView)
        })();
    </script>
@endif

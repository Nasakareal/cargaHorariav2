<li @isset($item['id']) id="{{ $item['id'] }}" @endisset class="nav-item has-treeview {{ $item['submenu_class'] }}">


    {{-- Botón padre --}}
    <a class="nav-link bg-primary rounded mb-2 {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="" {!! $item['data-compiled'] ?? '' !!}
       style="color:#fff !important;">

        <i class="nav-icon {{ $item['icon'] ?? 'far fa-fw fa-circle' }} {{
            isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''
        }}"></i>

        <p>
            {{ $item['text'] }}
            <i class="fas fa-angle-left right"></i>

            @isset($item['label'])
                <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} right">
                    {{ $item['label'] }}
                </span>
            @endisset
        </p>
    </a>

    
    {{-- Submenú: fondo sólido + texto blanco --}}
    <ul class="nav nav-treeview nav-treeview-solid" style="background-color:#007D80 !important;">
        @each('adminlte::partials.sidebar.menu-item', $item['submenu'], 'item')
    </ul>

    @once
    <style>
      /* Texto e íconos blancos en submenús */
      .nav-treeview > .nav-item > .nav-link,
      .nav-treeview > .nav-item > .nav-link > p,
      .nav-treeview > .nav-item > .nav-link .nav-icon {
        color: #fff !important;
        opacity: 1 !important;
      }

      /* Hover */
      .nav-treeview > .nav-item > .nav-link:hover {
        background: rgba(255,255,255,.12) !important;
      }

      /* Activo resaltado */
      .nav-treeview > .nav-item > .nav-link.active {
        background: rgba(255,255,255,.25) !important;
        font-weight: bold;
        border-left: 3px solid #fff; /* Línea izquierda opcional */
      }


      /*

      {{-- Botón padre --}}
    <a class="nav-link rounded mb-2 {{ $item['class'] }} @isset($item['shift']) {{ $item['shift'] }} @endisset"
       href="" {!! $item['data-compiled'] ?? '' !!}
       style="background-color:#343a40 !important; color:#fff !important;">

        <i class="nav-icon {{ $item['icon'] ?? 'far fa-fw fa-circle' }} {{
            isset($item['icon_color']) ? 'text-'.$item['icon_color'] : ''
        }}"></i>

        <p>
            {{ $item['text'] }}
            <i class="fas fa-angle-left right"></i>

            @isset($item['label'])
                <span class="badge badge-{{ $item['label_color'] ?? 'primary' }} right">
                    {{ $item['label'] }}
                </span>
            @endisset
        </p>
    </a>


      */
    </style>
    @endonce

</li>

@props(['nodes', 'activePath' => [], 'depth' => 1])
{{--
    Recursive category tree component — design spec class names.
    $nodes      : array of tree nodes (objects with ->id, ->name, ->children)
    $activePath : array of category IDs from selected node up to root
    $depth      : 1–4 (controls indent + ink + size via nb-tree--l{n})
--}}
<ul class="nb-tree nb-tree--l{{ $depth }}">
    @foreach ($nodes as $node)
        @php
            $hasChildren = !empty($node->children);
            $isOpen      = in_array($node->id, $activePath, true);
            $isActive    = ($activePath !== [] && $node->id === end($activePath));
        @endphp
        <li>
            <a class="nb-tree__row{{ $isActive ? ' is-active' : '' }}"
               href="{{ route('catalog.index', ['category' => $node->id]) }}"
               @if($hasChildren) aria-expanded="{{ $isOpen ? 'true' : 'false' }}" @endif>
                @if($hasChildren)
                    <span class="nb-tree__chev" aria-hidden="true">&#9656;</span>
                @endif
                <span>{{ $node->name }}</span>
            </a>
            @if($hasChildren && $depth < 4)
                <div class="nb-tree__branch" @unless($isOpen) hidden @endunless>
                    <x-category-tree
                        :nodes="$node->children"
                        :active-path="$activePath"
                        :depth="$depth + 1"
                    />
                </div>
            @endif
        </li>
    @endforeach
</ul>

@props(['nodes', 'activePath' => [], 'depth' => 1])
{{--
    Recursive category tree component — design spec class names.
    $nodes      : array of tree nodes (objects with ->id, ->name, ->url_slug, ->children)
                  May be kaspi (node_type='kaspi') or manual (node_type='manual') nodes.
                  Legacy kaspi nodes (from buildTree) have no node_key/node_type set.
    $activePath : array of node_keys ('kaspi:{id}' or 'manual:{id}') from selected node to root
    $depth      : 1–4 (controls indent + ink + size via nb-tree--l{n})
--}}
<ul class="nb-tree nb-tree--l{{ $depth }}">
    @foreach ($nodes as $node)
        @php
            $nodeType    = $node->node_type ?? 'kaspi';
            $nodeKey     = $node->node_key  ?? ('kaspi:' . $node->id);
            $hasChildren = !empty($node->children);
            $isOpen      = in_array($nodeKey, $activePath, true);
            $isActive    = ($activePath !== [] && $nodeKey === end($activePath));
            if ($nodeType === 'manual') {
                $href = route('manual-category.show', ['slug' => $node->url_slug]);
            } else {
                $href = !empty($node->url_slug)
                    ? route('catalog.show',  ['slug' => $node->url_slug])
                    : route('catalog.index', ['category' => $node->id]);
            }
        @endphp
        <li>
            <a class="nb-tree__row{{ $isActive ? ' is-active' : '' }}"
               href="{{ $href }}"
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

@props(['nodes', 'activePath' => [], 'depth' => 1])
{{--
    Recursive category tree component — design spec class names.
    $nodes      : array of tree nodes (objects with ->id, ->name, ->url_slug, ->children)
                  May be kaspi (node_type='kaspi') or manual (node_type='manual') nodes.
                  Legacy kaspi nodes (from buildTree) have no node_key/node_type set.
    $activePath : array of node_keys ('kaspi:{id}' or 'manual:{id}') from selected node to root
    $depth      : 1–4 (controls indent + ink + size via nb-tree--l{n})

    For parent nodes the row is split into:
      - <a class="nb-tree__link">   → navigates to the category page
      - <button class="nb-tree__toggler"> → expands/collapses children (no navigation)
    Leaf nodes are plain <a class="nb-tree__row"> anchors.
    No nested interactive elements (no button inside a, no a inside button).
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
            $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '-', $nodeKey);
        @endphp
        <li>
            @if($hasChildren)
                {{-- Parent: link navigates, button toggles children --}}
                <div class="nb-tree__row{{ $isActive ? ' is-active' : '' }}">
                    <a class="nb-tree__link" href="{{ $href }}">{{ $node->name }}</a>
                    <button class="nb-tree__toggler"
                            aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                            aria-controls="nbt-{{ $safeKey }}"
                            aria-label="{{ $node->name }}">
                        <span class="nb-tree__chev" aria-hidden="true">&#9656;</span>
                    </button>
                </div>
                @if($depth < 4)
                    <div class="nb-tree__branch" id="nbt-{{ $safeKey }}" @unless($isOpen) hidden @endunless>
                        <x-category-tree
                            :nodes="$node->children"
                            :active-path="$activePath"
                            :depth="$depth + 1"
                        />
                    </div>
                @endif
            @else
                {{-- Leaf: plain anchor --}}
                <a class="nb-tree__row{{ $isActive ? ' is-active' : '' }}" href="{{ $href }}">
                    {{ $node->name }}
                </a>
            @endif
        </li>
    @endforeach
</ul>

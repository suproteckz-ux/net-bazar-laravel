{{--
    Recursive category tree partial.
    Called as: @include('partials.category-tree', ['nodes' => $someArray])
    Inherits from parent scope: $activeBranchIds, $categoryId (selected category ID or '').
--}}
<ul class="cat-tree-list">
    @foreach($nodes as $cat)
        @php
            $isActive   = ($categoryId ?? '') === $cat->id;
            $inBranch   = in_array($cat->id, $activeBranchIds ?? []);
            $hasChildren = !empty($cat->children);
        @endphp
        <li class="cat-tree-item">
            @if($hasChildren)
                <details class="cat-tree-group" @if($inBranch) open @endif>
                    <summary class="cat-tree-parent-label">
                        {{-- Chevron rotates open/close via CSS --}}
                        <svg class="cat-tree-chevron" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                            <path d="M3 2l4 3-4 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        {{ $cat->name }}
                    </summary>
                    {{-- "All products in section" link — mirrors Python's "Все товары раздела" --}}
                    <a class="cat-tree-link cat-tree-all {{ $isActive ? 'is-active' : '' }}"
                       href="{{ route('catalog.index', ['category' => $cat->id]) }}">
                        Все товары раздела
                    </a>
                    @include('partials.category-tree', ['nodes' => $cat->children])
                </details>
            @else
                <a class="cat-tree-link {{ $isActive ? 'is-active' : '' }}"
                   href="{{ route('catalog.index', ['category' => $cat->id]) }}">
                    {{ $cat->name }}
                </a>
            @endif
        </li>
    @endforeach
</ul>

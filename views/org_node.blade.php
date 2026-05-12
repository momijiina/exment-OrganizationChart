{{-- 組織図ノード（再帰コンポーネント） --}}
@php
    $hasChildren = !empty($node['children']);
    $nodeClass = 'org-node';

    if ($level === 0) {
        $nodeClass .= ' org-node-root';
    } elseif ($level === 1) {
        $nodeClass .= ' org-node-level2';
    } elseif ($level >= 2) {
        $nodeClass .= ' org-node-deep';
    }

    $getChildrenLayout = null;
    $getChildCenters = function($childWidths, $spacersAfter) {
        $centers = [];
        $cursor = 0;

        foreach ($childWidths as $index => $width) {
            $centers[$index] = $cursor + ($width / 2);
            $cursor += $width + ($spacersAfter[$index] ?? 0);
        }

        return $centers;
    };

    $getNodeBranchWidth = function($target) use (&$getNodeBranchWidth, &$getChildrenLayout) {
        $baseSlot = 190;
        if (empty($target['children'])) {
            return $baseSlot;
        }

        $layout = $getChildrenLayout($target['children']);
        return max($baseSlot, $layout['width']);
    };

    $getChildrenLayout = function($children) use (&$getNodeBranchWidth, $getChildCenters) {
        $childWidths = [];
        foreach ($children as $childNode) {
            $childWidths[] = $getNodeBranchWidth($childNode);
        }

        $offset = 0;
        $spacersAfter = [];
        $count = count($childWidths);
        $totalWidth = array_sum($childWidths);

        if ($count > 1) {
            if ($count % 2 === 1) {
                $middleIndex = intdiv($count, 2);
                $centers = $getChildCenters($childWidths, $spacersAfter);
                $childrenCenter = $centers[$middleIndex];
            } else {
                $centers = $getChildCenters($childWidths, $spacersAfter);
                $rightMiddleIndex = intdiv($count, 2);
                $leftMiddleIndex = $rightMiddleIndex - 1;
                $childrenCenter = ($centers[$leftMiddleIndex] + $centers[$rightMiddleIndex]) / 2;
            }

            $offset = (int)round(($totalWidth / 2) - $childrenCenter);
        }

        $leftExtent = ($totalWidth / 2) - $offset;
        $rightExtent = ($totalWidth / 2) + $offset;
        $width = (int)ceil(max(95, $leftExtent, $rightExtent) * 2);

        return [
            'child_widths' => $childWidths,
            'spacers_after' => $spacersAfter,
            'offset' => $offset,
            'width' => $width,
        ];
    };

    $layout = [
        'child_widths' => [],
        'spacers_after' => [],
        'offset' => 0,
    ];
    if ($hasChildren) {
        $layout = $getChildrenLayout($node['children']);
    }
@endphp

<div class="org-node-wrap {{ $hasChildren ? 'has-children' : '' }}">
    {{-- ノードボックス --}}
    <a href="{{ $node['url'] ?? '#' }}" class="{{ $nodeClass }}" title="{{ $node['name'] }}">
        {{ $node['name'] }}
    </a>

    {{-- 子ノードがある場合 --}}
    @if($hasChildren)
        <ul style="--org-branch-offset: {{ $layout['offset'] }}px;">
            @foreach($node['children'] as $index => $child)
                <li style="--org-branch-width: {{ $layout['child_widths'][$index] ?? 190 }}px;">
                    @include('exment_organization_chart::org_node', ['node' => $child, 'level' => $level + 1])
                </li>
                @if(!empty($layout['spacers_after'][$index]))
                    <li class="org-branch-spacer" style="--org-branch-width: {{ $layout['spacers_after'][$index] }}px;"></li>
                @endif
            @endforeach
        </ul>
    @endif
</div>

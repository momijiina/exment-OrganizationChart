<?php

namespace App\Plugins\OrganizationChart;

use Exceedone\Exment\Services\Plugin\PluginViewBase;

class Plugin extends PluginViewBase
{
    /**
     * 組織図ビューのメイン表示
     *
     * @return string
     */
    public function grid()
    {
        $tree = $this->getItems();
        return $this->generateOrgChartHtml($tree);
    }

    /**
     * ビューのオプション設定フォーム
     *
     * @param mixed $form
     * @return void
     */
    public function setViewOptionForm($form)
    {
        // フィルタ(絞り込み)の設定
        static::setFilterFields($form, $this->custom_table);

        // 並べ替えの設定
        static::setSortFields($form, $this->custom_table);
    }

    /**
     * カスタムテーブルからデータを取得し、ツリー構造に変換する
     * Exmentデフォルトの組織テーブル列（organization_name, parent_organization）を使用
     *
     * @return array
     */
    protected function getItems()
    {
        $query = $this->custom_table->getValueQuery();

        // データのフィルタを実施
        $this->custom_view->filterModel($query);

        // データのソートを実施
        $this->custom_view->sortModel($query);

        // 値を取得
        $items = collect();
        $query->chunk(1000, function($values) use(&$items) {
            $items = $items->merge($values);
        });

        // フラットなデータ一覧を作成
        $flatList = [];
        foreach ($items as $item) {
            // 組織名を取得（organization_name列 → getLabel のフォールバック）
            $name = $item->getValue('organization_name', true);
            if (empty($name)) {
                $name = $item->getLabel();
            }

            // 親組織を取得（parent_organization は「組織」参照列）
            $parentValue = $item->getValue('parent_organization');
            $parentId = null;
            if ($parentValue) {
                if (is_object($parentValue)) {
                    $parentId = $parentValue->id ?? null;
                } elseif (is_array($parentValue)) {
                    // 配列の場合は最初の要素を使用
                    $first = reset($parentValue);
                    $parentId = is_object($first) ? ($first->id ?? null) : $first;
                } elseif (is_numeric($parentValue)) {
                    $parentId = intval($parentValue);
                } else {
                    $parentId = $parentValue;
                }
            }

            // 組織コード（表示順に利用）
            $sortValue = $item->getValue('organization_code') ?? $item->id;

            $flatList[] = [
                'id' => $item->id,
                'name' => $name,
                'parent_id' => $parentId,
                'sort' => $sortValue,
                'url' => $item->getUrl(),
            ];
        }

        // ソート
        usort($flatList, function($a, $b) {
            return strval($a['sort'] ?? '') <=> strval($b['sort'] ?? '');
        });

        // ツリー構造に変換
        $tree = $this->buildTree($flatList, null);

        return $tree;
    }

    /**
     * フラットなリストからツリー構造を構築
     *
     * @param array $items
     * @param mixed $parentId
     * @return array
     */
    protected function buildTree(array $items, $parentId)
    {
        $branch = [];

        foreach ($items as $item) {
            $itemParentId = $item['parent_id'];

            $isMatch = false;
            if ($parentId === null) {
                $isMatch = ($itemParentId === null || $itemParentId === '' || $itemParentId === 0);
            } else {
                $isMatch = ($itemParentId == $parentId);
            }

            if ($isMatch) {
                $children = $this->buildTree($items, $item['id']);
                $item['children'] = $children;
                $branch[] = $item;
            }
        }

        return $branch;
    }

    /**
     * 組織図のHTML全体を生成
     *
     * @param array $tree
     * @return string
     */
    protected function generateOrgChartHtml(array $tree)
    {
        $css = $this->generateCss();
        $toolbar = $this->generateToolbar();

        if (empty($tree)) {
            $content = '
            <div class="org-chart-empty">
                <i class="fa fa-sitemap"></i>
                <p>組織データがありません。</p>
            </div>';
        } else {
            $nodesHtml = '';
            foreach ($tree as $item) {
                $nodesHtml .= $this->renderNode($item, 0);
            }
            $content = '
            <div class="org-chart-wrapper">
                <div class="org-chart-zoom-wrapper" id="orgChartZoomWrapper">
                    <div class="org-tree">
                        ' . $nodesHtml . '
                    </div>
                </div>
            </div>';
        }

        $script = $this->generateScript();

        return $css . $toolbar . '
        <div class="org-chart-container" id="orgChartContainer">
            ' . $content . '
        </div>
        ' . $script;
    }

    /**
     * ノードを再帰的にHTMLとしてレンダリング
     *
     * @param array $node
     * @param int $level
     * @return string
     */
    protected function renderNode(array $node, int $level)
    {
        $hasChildren = !empty($node['children']);
        $name = e($node['name']);
        $url = e($node['url'] ?? '#');

        // レベルに応じたCSSクラス（ピラミッド型：全て横書き）
        $nodeClass = 'org-node org-node-level' . min($level, 4);

        $wrapClass = 'org-node-wrap' . ($hasChildren ? ' has-children' : '');
        $html = '<div class="' . $wrapClass . '">';
        $html .= '<a href="' . $url . '" class="' . $nodeClass . '" title="' . $name . '">' . $name . '</a>';

        if ($hasChildren) {
            $layout = $this->getChildrenLayout($node['children']);
            $html .= '<ul style="--org-branch-offset: ' . $layout['offset'] . 'px;">';
            foreach ($node['children'] as $index => $child) {
                $childWidth = $layout['child_widths'][$index] ?? 190;
                $html .= '<li style="--org-branch-width: ' . $childWidth . 'px;">' . $this->renderNode($child, $level + 1) . '</li>';
                if (!empty($layout['spacers_after'][$index])) {
                    $html .= '<li class="org-branch-spacer" style="--org-branch-width: ' . $layout['spacers_after'][$index] . 'px;"></li>';
                }
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * 子ノード群の実幅と、中央補正用の余白を計算する。
     *
     * @param array $children
     * @return array
     */
    protected function getChildrenLayout(array $children)
    {
        $childWidths = [];
        foreach ($children as $child) {
            $childWidths[] = $this->getNodeBranchWidth($child);
        }

        $offset = 0;
        $spacersAfter = [];
        $count = count($childWidths);
        $totalWidth = array_sum($childWidths);

        if ($count > 1) {
            if ($count % 2 === 1) {
                $middleIndex = intdiv($count, 2);
                $centers = $this->getChildCenters($childWidths, $spacersAfter);
                $childrenCenter = $centers[$middleIndex];
            } else {
                $centers = $this->getChildCenters($childWidths, $spacersAfter);
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
    }

    /**
     * 子ノードの中心位置を取得する。
     *
     * @param array $childWidths
     * @param array $spacersAfter
     * @return array
     */
    protected function getChildCenters(array $childWidths, array $spacersAfter)
    {
        $centers = [];
        $cursor = 0;

        foreach ($childWidths as $index => $width) {
            $centers[$index] = $cursor + ($width / 2);
            $cursor += $width + ($spacersAfter[$index] ?? 0);
        }

        return $centers;
    }

    /**
     * ノード配下の表示に必要な枝幅を計算する。
     *
     * @param array $node
     * @return int
     */
    protected function getNodeBranchWidth(array $node)
    {
        $baseSlot = 190;

        if (empty($node['children'])) {
            return $baseSlot;
        }

        $layout = $this->getChildrenLayout($node['children']);
        return max($baseSlot, $layout['width']);
    }

    /**
     * ツールバーHTML
     *
     * @return string
     */
    protected function generateToolbar()
    {
        return '
        <div class="org-chart-toolbar">
            <button class="btn btn-default btn-sm" onclick="orgChartZoom(\'in\')" title="拡大">
                <i class="fa fa-search-plus"></i>
            </button>
            <button class="btn btn-default btn-sm" onclick="orgChartZoom(\'out\')" title="縮小">
                <i class="fa fa-search-minus"></i>
            </button>
            <button class="btn btn-default btn-sm" onclick="orgChartZoom(\'reset\')" title="リセット">
                <i class="fa fa-refresh"></i>
            </button>
            <button class="btn btn-default btn-sm" onclick="orgChartFullscreen()" title="全画面">
                <i class="fa fa-expand"></i>
            </button>
        </div>';
    }

    /**
     * JavaScript
     *
     * @return string
     */
    protected function generateScript()
    {
        return '
        <script>
        (function() {
            var currentZoom = 1;
            var minZoom = 0.3;
            var maxZoom = 2;
            var panX = 0;
            var panY = 0;
            var isDragging = false;
            var hasDragged = false;
            var dragStartX = 0;
            var dragStartY = 0;
            var panStartX = 0;
            var panStartY = 0;

            function getWrapper() {
                return document.getElementById("orgChartZoomWrapper");
            }

            function applyTransform() {
                var wrapper = getWrapper();
                if (!wrapper) return;
                wrapper.style.transform = "translate(" + panX + "px, " + panY + "px) scale(" + currentZoom + ")";
            }

            window.orgChartZoom = function(action) {
                if (action === "in") {
                    currentZoom = Math.min(currentZoom + 0.1, maxZoom);
                } else if (action === "out") {
                    currentZoom = Math.max(currentZoom - 0.1, minZoom);
                } else if (action === "reset") {
                    currentZoom = 1;
                    panX = 0;
                    panY = 0;
                }
                applyTransform();
            };

            window.orgChartFullscreen = function() {
                var container = document.getElementById("orgChartContainer");
                if (!container) return;
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else {
                    container.requestFullscreen().catch(function() {});
                }
            };

            var container = document.getElementById("orgChartContainer");
            if (container) {
                container.addEventListener("wheel", function(e) {
                    if (e.ctrlKey) {
                        e.preventDefault();
                        if (e.deltaY < 0) {
                            window.orgChartZoom("in");
                        } else {
                            window.orgChartZoom("out");
                        }
                    }
                }, { passive: false });

                container.addEventListener("mousedown", function(e) {
                    if (e.button !== 0) return;
                    if (!getWrapper()) return;

                    isDragging = true;
                    hasDragged = false;
                    dragStartX = e.clientX;
                    dragStartY = e.clientY;
                    panStartX = panX;
                    panStartY = panY;
                    container.classList.add("is-panning");
                    e.preventDefault();
                });

                document.addEventListener("mousemove", function(e) {
                    if (!isDragging) return;

                    var dx = e.clientX - dragStartX;
                    var dy = e.clientY - dragStartY;

                    if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                        hasDragged = true;
                    }

                    panX = panStartX + dx;
                    panY = panStartY + dy;
                    applyTransform();
                });

                document.addEventListener("mouseup", function() {
                    if (!isDragging) return;
                    isDragging = false;
                    container.classList.remove("is-panning");
                });

                container.addEventListener("click", function(e) {
                    if (!hasDragged) return;
                    e.preventDefault();
                    e.stopPropagation();
                    hasDragged = false;
                }, true);
            }
        })();
        </script>';
    }

    /**
     * CSS
     *
     * @return string
     */
    protected function generateCss()
    {
        return '
        <style>
        /* ===== 組織図プラグイン ===== */
        .org-chart-container {
            --org-branch-slot: 190px;
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            padding: 30px 20px 40px 20px;
            background: #f5f7fb;
            border-radius: 6px;
            min-height: 300px;
            cursor: grab;
            user-select: none;
        }
        .org-chart-container.is-panning {
            cursor: grabbing;
        }
        .org-chart-container.is-panning .org-chart-zoom-wrapper {
            transition: none;
        }
        .org-chart-wrapper {
            display: flex;
            justify-content: center;
            min-width: fit-content;
        }
        /* ===== ツリー ===== */
        .org-tree {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .org-tree ul {
            display: flex;
            justify-content: center;
            padding: 0;
            margin: 0;
            list-style: none;
            position: relative;
            overflow: visible;
            transform: translateX(var(--org-branch-offset, 0px));
        }
        .org-tree li {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: var(--org-branch-width, var(--org-branch-slot));
            padding: 0;
            overflow: visible;
            box-sizing: border-box;
        }
        /* ===== 親→子の接続線（親ノードの下から伸びる縦線） ===== */
        .org-node-wrap.has-children > .org-node {
            margin-bottom: 22px;
        }
        .org-node-wrap.has-children > .org-node::after {
            content: "";
            position: absolute;
            bottom: -22px;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 22px;
            background: #b0c4de;
        }
        /* ===== 各子ノードへの縦線（水平バーから子ノードへ） ===== */
        .org-tree li::before {
            content: "";
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 22px;
            background: #b0c4de;
        }
        /* ===== 兄弟間の水平線 ===== */
        .org-tree li::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 0;
            border-top: 2px solid #b0c4de;
        }
        .org-tree li:first-child::after { left: 50%; width: 50%; }
        .org-tree li:last-child::after { width: 50%; }
        .org-tree li:only-child::after { display: none; }
        .org-tree li.org-branch-spacer::before { display: none; }
        /* ===== ノード共通 ===== */
        .org-node {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            padding: 11px 22px;
            margin-top: 22px;
            margin-bottom: 4px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            z-index: 1;
        }
        .org-node:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }
        .org-node:active { transform: translateY(0); }
        /* ----- Level 0: ルート（濃いブルー） ----- */
        .org-node.org-node-level0 {
            min-width: 160px;
            padding: 14px 32px;
            background: #1a3a5c;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(26,58,92,0.35);
        }
        .org-node.org-node-level0:hover {
            background: #15304d;
            box-shadow: 0 6px 20px rgba(26,58,92,0.45);
            color: #ffffff;
        }
        /* ----- Level 1: 部門（メインブルー） ----- */
        .org-node.org-node-level1 {
            min-width: 130px;
            padding: 12px 26px;
            background: #2b6cb0;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(43,108,176,0.3);
        }
        .org-node.org-node-level1:hover {
            background: #225a94;
            box-shadow: 0 5px 16px rgba(43,108,176,0.4);
            color: #ffffff;
        }
        /* ----- Level 2: 部署（ミディアムブルー） ----- */
        .org-node.org-node-level2 {
            min-width: 110px;
            padding: 10px 20px;
            background: #4a90d9;
            color: #ffffff;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(74,144,217,0.3);
        }
        .org-node.org-node-level2:hover {
            background: #3a7cc4;
            box-shadow: 0 4px 14px rgba(74,144,217,0.4);
            color: #ffffff;
        }
        /* ----- Level 3: メンバー（ライトブルー） ----- */
        .org-node.org-node-level3 {
            min-width: 100px;
            padding: 9px 18px;
            background: #a8c8e8;
            color: #1a3a5c;
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(168,200,232,0.4);
        }
        .org-node.org-node-level3:hover {
            background: #8fb8de;
            box-shadow: 0 4px 12px rgba(168,200,232,0.5);
            color: #1a3a5c;
        }
        /* ----- Level 4+: それ以上深い階層 ----- */
        .org-node.org-node-level4 {
            min-width: 90px;
            padding: 8px 16px;
            background: #d0e2f2;
            color: #2b5278;
            font-size: 12px;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(208,226,242,0.5);
        }
        .org-node.org-node-level4:hover {
            background: #b8d4ec;
            box-shadow: 0 3px 10px rgba(208,226,242,0.6);
            color: #1a3a5c;
        }
        /* ===== ノードラッパー ===== */
        .org-node-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ===== 空 ===== */
        .org-chart-empty {
            text-align: center;
            padding: 60px 20px;
            color: #95a5a6;
            font-size: 15px;
        }
        .org-chart-empty i {
            display: block;
            font-size: 48px;
            margin-bottom: 16px;
            color: #bdc3c7;
        }
        /* ===== ツールバー ===== */
        .org-chart-toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 16px;
            margin-bottom: 0;
            background: #eef2f7;
            border-bottom: 1px solid #dde4ec;
            border-radius: 6px 6px 0 0;
            gap: 8px;
        }
        .org-chart-toolbar .btn {
            padding: 4px 12px;
            font-size: 12px;
            border-radius: 4px;
        }
        /* ===== ズーム ===== */
        .org-chart-zoom-wrapper {
            transform-origin: top center;
            transition: transform 0.3s ease;
        }
        @media print {
            .org-chart-toolbar { display: none; }
            .org-chart-container { box-shadow: none; overflow: visible; background: #fff; }
        }
        @media (max-width: 768px) {
            .org-chart-container { --org-branch-slot: 104px; }
            .org-node { min-width: 70px; padding: 6px 10px; font-size: 11px; }
            .org-node.org-node-level0 { min-width: 100px; padding: 8px 16px; font-size: 13px; }
        }
        </style>';
    }
}

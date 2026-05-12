<style>
/* ===== 組織図プラグイン スタイル ===== */
.org-chart-container {
    --org-branch-slot: 190px;
    width: 100%;
    overflow-x: auto;
    overflow-y: auto;
    padding: 30px 20px 40px 20px;
    background: #ffffff;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
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

/* ===== ツリー構造 ===== */
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

/* ===== 接続線（縦線） ===== */
.org-tree li::before,
.org-tree li::after {
    content: '';
    position: absolute;
    top: 0;
    border-top: 2px solid #8b9dc3;
    width: 50%;
    height: 20px;
}

.org-tree li::before {
    right: 50%;
    border-right: 2px solid #8b9dc3;
}

.org-tree li::after {
    left: 50%;
    border-left: 2px solid #8b9dc3;
}

/* 最初の子：左側の横線を消す */
.org-tree li:first-child::before {
    border-top: none;
}

/* 最後の子：右側の横線を消す */
.org-tree li:last-child::after {
    border-top: none;
}

/* 一人っ子：両横線を消す */
.org-tree li:only-child::before,
.org-tree li:only-child::after {
    border-top: none;
}

.org-tree li.org-branch-spacer::before {
    display: none;
}

/* ===== ノードから子への縦線 ===== */
.org-tree ul::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 0;
    height: 20px;
    border-left: 2px solid #8b9dc3;
}

/* ルートの場合は上への線なし */
.org-tree > .org-node-wrap > ul::before {
    display: block;
}

/* ===== ノードボックス ===== */
.org-node {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 100px;
    padding: 10px 18px;
    margin-top: 20px;
    margin-bottom: 4px;
    border: 2px solid #4a6fa5;
    border-radius: 6px;
    background: linear-gradient(135deg, #f8faff 0%, #edf2fb 100%);
    color: #2c3e50;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 2px 6px rgba(74, 111, 165, 0.15);
    white-space: nowrap;
    z-index: 1;
}

.org-node:hover {
    background: linear-gradient(135deg, #4a6fa5 0%, #3a5a8c 100%);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(74, 111, 165, 0.35);
    text-decoration: none;
}

.org-node:active {
    transform: translateY(0);
}

/* ルートノード */
.org-node.org-node-root {
    min-width: 140px;
    padding: 14px 24px;
    border: 2px solid #2c3e50;
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(44, 62, 80, 0.3);
}

.org-node.org-node-root:hover {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    box-shadow: 0 8px 20px rgba(44, 62, 80, 0.4);
    transform: translateY(-3px);
}

/* レベル2ノード */
.org-node.org-node-level2 {
    border-color: #5b8dbf;
    background: linear-gradient(135deg, #e8f0fe 0%, #d4e4f7 100%);
    font-size: 13px;
}

.org-node.org-node-level2:hover {
    background: linear-gradient(135deg, #5b8dbf 0%, #4a7aad 100%);
    color: #ffffff;
}

/* レベル3以降のノード（縦書き対応） */
.org-node.org-node-deep {
    writing-mode: vertical-rl;
    text-orientation: upright;
    min-width: unset;
    min-height: 80px;
    padding: 16px 10px;
    font-size: 12px;
    letter-spacing: 2px;
    border-color: #7fa8c9;
    background: linear-gradient(180deg, #f0f6fc 0%, #dce8f4 100%);
}

.org-node.org-node-deep:hover {
    background: linear-gradient(180deg, #7fa8c9 0%, #6a94b8 100%);
    color: #ffffff;
}

/* ===== ノードの子供用ラッパー ===== */
.org-node-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* 子ノードがある場合のノード下の縦線 */
.org-node-wrap.has-children > .org-node::after {
    content: '';
    position: absolute;
    bottom: -24px;
    left: 50%;
    width: 0;
    height: 24px;
    border-left: 2px solid #8b9dc3;
}

/* ===== サイドノード（監査役など横に表示するもの） ===== */
.org-side-container {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 0;
    position: relative;
}

.org-side-node {
    position: relative;
    display: flex;
    align-items: center;
}

.org-side-node::before {
    content: '';
    display: inline-block;
    width: 40px;
    height: 0;
    border-top: 2px solid #8b9dc3;
    margin-right: 0;
}

/* ===== 空メッセージ ===== */
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
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
    border-radius: 4px 4px 0 0;
    gap: 8px;
}

.org-chart-toolbar .btn {
    padding: 4px 12px;
    font-size: 12px;
    border-radius: 4px;
}

/* ===== ズーム制御 ===== */
.org-chart-zoom-wrapper {
    transform-origin: top center;
    transition: transform 0.3s ease;
}

/* ===== 印刷対応 ===== */
@media print {
    .org-chart-toolbar {
        display: none;
    }
    .org-chart-container {
        box-shadow: none;
        overflow: visible;
    }
}

/* ===== レスポンシブ ===== */
@media (max-width: 768px) {
    .org-chart-container {
        --org-branch-slot: 104px;
    }
    .org-node {
        min-width: 70px;
        padding: 6px 10px;
        font-size: 11px;
    }
    .org-node.org-node-root {
        min-width: 100px;
        padding: 8px 14px;
        font-size: 13px;
    }
    .org-node.org-node-deep {
        padding: 10px 6px;
        font-size: 10px;
        min-height: 60px;
    }
}
</style>

<div class="org-chart-toolbar">
    <button class="btn btn-default btn-sm" onclick="orgChartZoom('in')" title="拡大">
        <i class="fa fa-search-plus"></i>
    </button>
    <button class="btn btn-default btn-sm" onclick="orgChartZoom('out')" title="縮小">
        <i class="fa fa-search-minus"></i>
    </button>
    <button class="btn btn-default btn-sm" onclick="orgChartZoom('reset')" title="リセット">
        <i class="fa fa-refresh"></i>
    </button>
    <button class="btn btn-default btn-sm" onclick="orgChartFullscreen()" title="全画面">
        <i class="fa fa-expand"></i>
    </button>
</div>

<div class="org-chart-container" id="orgChartContainer">
    @if(empty($items))
        <div class="org-chart-empty">
            <i class="fa fa-sitemap"></i>
            組織データがありません。<br>
            カスタムビューの設定で「組織名列」と「親組織列」を正しく設定してください。
        </div>
    @else
        <div class="org-chart-wrapper">
            <div class="org-chart-zoom-wrapper" id="orgChartZoomWrapper">
                <div class="org-tree">
                    @foreach($items as $item)
                        @include('exment_organization_chart::org_node', ['node' => $item, 'level' => 0])
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

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
        return document.getElementById('orgChartZoomWrapper');
    }

    function applyTransform() {
        var wrapper = getWrapper();
        if (!wrapper) return;
        wrapper.style.transform = 'translate(' + panX + 'px, ' + panY + 'px) scale(' + currentZoom + ')';
    }

    window.orgChartZoom = function(action) {
        if (action === 'in') {
            currentZoom = Math.min(currentZoom + 0.1, maxZoom);
        } else if (action === 'out') {
            currentZoom = Math.max(currentZoom - 0.1, minZoom);
        } else if (action === 'reset') {
            currentZoom = 1;
            panX = 0;
            panY = 0;
        }

        applyTransform();
    };

    window.orgChartFullscreen = function() {
        var container = document.getElementById('orgChartContainer');
        if (!container) return;

        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            container.requestFullscreen().catch(function() {});
        }
    };

    // マウスホイールでのズーム
    var container = document.getElementById('orgChartContainer');
    if (container) {
        container.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
                if (e.deltaY < 0) {
                    window.orgChartZoom('in');
                } else {
                    window.orgChartZoom('out');
                }
            }
        }, { passive: false });

        container.addEventListener('mousedown', function(e) {
            if (e.button !== 0) return;
            if (!getWrapper()) return;

            isDragging = true;
            hasDragged = false;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            panStartX = panX;
            panStartY = panY;
            container.classList.add('is-panning');
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
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

        document.addEventListener('mouseup', function() {
            if (!isDragging) return;
            isDragging = false;
            container.classList.remove('is-panning');
        });

        container.addEventListener('click', function(e) {
            if (!hasDragged) return;
            e.preventDefault();
            e.stopPropagation();
            hasDragged = false;
        }, true);
    }
})();
</script>

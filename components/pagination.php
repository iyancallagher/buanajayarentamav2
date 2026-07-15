<?php

$currentPage = max(1, (int) ($currentPage ?? 1));
$totalRows   = $totalRows ?? 0;
$perPage     = $perPage ?? 10;
$baseQuery   = $baseQuery ?? [];

$totalPages = (int) ceil($totalRows / $perPage);

// Helper bikin URL halaman tertentu, sambil bawa query string lain (search, dll)
function paginationUrl(int $page, array $baseQuery): string
{
    $query = array_merge($baseQuery, ['page' => $page]);
    return '?' . http_build_query($query);
}

// Hitung rentang nomor halaman yang ditampilkan (maks 5 angka, dengan elipsis)
$range = 2; // jumlah halaman di kiri & kanan halaman aktif
$start = max(1, $currentPage - $range);
$end   = min($totalPages, $currentPage + $range);

// Hitung "Menampilkan X-Y dari Z data"
$fromRow = $totalRows === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
$toRow   = min($currentPage * $perPage, $totalRows);
?>

<?php if ($totalRows > 0): ?>
<div class="block clear-both w-full px-5 py-4">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 w-full">

        <p class="text-sm text-slate-500 order-2 sm:order-1 text-center sm:text-left">
            Menampilkan <span class="font-medium text-slate-700"><?= $fromRow ?>-<?= $toRow ?></span>
            dari <span class="font-medium text-slate-700"><?= $totalRows ?></span> data
        </p>

        <?php if ($totalPages > 1): ?>
        <div class="block order-1 sm:order-2 text-center whitespace-nowrap">

            <a href="<?= $currentPage > 1 ? paginationUrl($currentPage - 1, $baseQuery) : '#' ?>"
                class="inline-block align-middle text-center px-2.5 py-1.5 mx-0.5 rounded-lg border border-slate-200 text-slate-500 text-sm font-medium transition-colors bg-white
                    <?= $currentPage <= 1 ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'hover:bg-slate-50 hover:text-slate-700' ?>">
                <i class="ti ti-chevron-left text-base leading-none align-middle"></i>
            </a>

            <?php if ($start > 1): ?>
                <a href="<?= paginationUrl(1, $baseQuery) ?>"
                    class="inline-block align-middle text-center px-3 py-1.5 mx-0.5 rounded-lg text-sm font-medium text-slate-500 bg-white hover:bg-slate-50 transition-colors border border-slate-200">
                    1
                </a>
                <?php if ($start > 2): ?>
                    <span class="inline-block align-middle text-center px-1.5 py-1.5 text-slate-400 text-sm">&bull;&bull;&bull;</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= paginationUrl($i, $baseQuery) ?>"
                    class="inline-block align-middle text-center px-3 py-1.5 mx-0.5 rounded-lg text-sm font-medium transition-all
                        <?= $i === $currentPage
                            ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-sm shadow-blue-500/25 font-bold border border-blue-500'
                            : 'text-slate-500 bg-white hover:bg-slate-50 border border-slate-200' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?>
                    <span class="inline-block align-middle text-center px-1.5 py-1.5 text-slate-400 text-sm">&bull;&bull;&bull;</span>
                <?php endif; ?>
                <a href="<?= paginationUrl($totalPages, $baseQuery) ?>"
                    class="inline-block align-middle text-center px-3 py-1.5 mx-0.5 rounded-lg text-sm font-medium text-slate-500 bg-white hover:bg-slate-50 transition-colors border border-slate-200">
                    <?= $totalPages ?>
                </a>
            <?php endif; ?>

            <a href="<?= $currentPage < $totalPages ? paginationUrl($currentPage + 1, $baseQuery) : '#' ?>"
                class="inline-block align-middle text-center px-2.5 py-1.5 mx-0.5 rounded-lg border border-slate-200 text-slate-500 text-sm font-medium transition-colors bg-white
                    <?= $currentPage >= $totalPages ? 'opacity-40 cursor-not-allowed pointer-events-none' : 'hover:bg-slate-50 hover:text-slate-700' ?>">
                <i class="ti ti-chevron-right text-base leading-none align-middle"></i>
            </a>

        </div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>
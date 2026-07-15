<?php

$columns       = $columns ?? [];
$rows          = $rows ?? [];
$emptyMessage  = $emptyMessage ?? 'Belum ada data.';
$searchValue   = $searchValue ?? ($_GET['search'] ?? '');
$showSearch    = $showSearch ?? true;
$tableTitle    = $tableTitle ?? null;
$tableActions  = $tableActions ?? null;
?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200">
    <?php if ($tableTitle || $showSearch || $tableActions): ?>
    <div class="p-5 lg:p-6 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <?php if ($tableTitle): ?>
        <h3 class="font-semibold text-sl text-slate-800 shrink-0">
            <?= htmlspecialchars($tableTitle) ?>
        </h3>
        <?php endif; ?>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">

            <?php if ($showSearch): ?>
            <form method="GET" class="relative w-full sm:w-64">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($searchValue) ?>"
                    placeholder="Cari data..."
                    class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm
                           placeholder:text-slate-400 transition-all
                           focus:outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
            </form>
            <?php endif; ?>

            <?php if ($tableActions): ?>
                <?= $tableActions ?>
            <?php endif; ?>

        </div>

    </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>

        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                <i class="ti ti-database-off text-3xl text-slate-400"></i>
            </div>
            <p class="text-slate-500 text-sm"><?= htmlspecialchars($emptyMessage) ?></p>
        </div>

    <?php else: ?>

        <!-- ===== DESKTOP: Table ===== -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50">
                        <?php foreach ($columns as $col): ?>
                        <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide
                            <?= ($col['align'] ?? 'left') === 'center' ? 'text-center' : 'text-left' ?>
                            <?= ($col['align'] ?? 'left') === 'right' ? 'text-right' : '' ?>">
                            <?= htmlspecialchars($col['label']) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr class="border-t border-slate-200 hover:bg-slate-50/50 transition-colors">
                        <?php foreach ($columns as $col):
                            $value = $row[$col['key']] ?? '-';
                        ?>
                        <td class="px-6 py-4 text-sm text-slate-700
                            <?= ($col['align'] ?? 'left') === 'center' ? 'text-center' : '' ?>
                            <?= ($col['align'] ?? 'left') === 'right' ? 'text-right' : '' ?>">
                            <?php if (!empty($col['raw'])): ?>
                                <?= $value ?>
                            <?php else: ?>
                                <?= htmlspecialchars((string) $value) ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ===== MOBILE: Card list ===== -->
        <div class="md:hidden divide-y divide-slate-200">
            <?php foreach ($rows as $row): ?>
            <div class="p-4 space-y-2">
                <?php foreach ($columns as $col):
                    $value = $row[$col['key']] ?? '-';
                ?>
                <div class="flex items-start justify-between gap-3">
                    <span class="text-xs font-medium text-slate-500 shrink-0">
                        <?= htmlspecialchars($col['label']) ?>
                    </span>
                    <span class="text-sm text-slate-800 text-right">
                        <?php if (!empty($col['raw'])): ?>
                            <?= $value ?>
                        <?php else: ?>
                            <?= htmlspecialchars((string) $value) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
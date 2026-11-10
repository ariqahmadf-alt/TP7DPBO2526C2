<?php
/**
 * view/partials/field.php
 *
 * Satu baris isian form (label + input + pesan error). Dipakai oleh semua form.
 * Variabel yang dibutuhkan:
 *   $f    array definisi field:
 *           name, label, type (text|number|email|tel|date|time|textarea|select),
 *           required (bool), default, options (untuk select: nilai => label),
 *           attrs (atribut HTML tambahan yang ditulis programmer), hint
 *   $item array data yang sedang diubah, atau null saat menambah
 * Nilai awal: input lama (bila validasi gagal) -> data yang diubah -> default.
 */
$fName  = $f['name'];
$fType  = $f['type'] ?? 'text';
$fValue = Flash::old($fName, $item[$fName] ?? ($f['default'] ?? ''));
$fError = Flash::fieldError($fName);
$fId    = 'f-' . $fName;
$fAttrs = ($f['attrs'] ?? '') . (!empty($f['required']) ? ' required' : '')
        . ($fError ? ' aria-invalid="true" aria-describedby="err-' . $fName . '"' : '');
?>
<div class="form-group<?= $fError ? ' has-error' : '' ?>">
    <label for="<?= $fId ?>">
        <?= Html::e($f['label']) ?>
        <?php if (!empty($f['required'])): ?><span class="req" aria-hidden="true">*</span><?php endif; ?>
    </label>

    <?php if ($fType === 'textarea'): ?>
        <textarea id="<?= $fId ?>" name="<?= $fName ?>" rows="4" <?= $fAttrs ?>><?= Html::e($fValue) ?></textarea>

    <?php elseif ($fType === 'select'): ?>
        <select id="<?= $fId ?>" name="<?= $fName ?>" <?= $fAttrs ?>>
            <?php foreach ($f['options'] as $optValue => $optLabel): ?>
                <option value="<?= Html::e($optValue) ?>"<?= (string) $fValue === (string) $optValue ? ' selected' : '' ?>>
                    <?= Html::e($optLabel) ?>
                </option>
            <?php endforeach; ?>
        </select>

    <?php else: ?>
        <input type="<?= Html::e($fType) ?>" id="<?= $fId ?>" name="<?= $fName ?>"
               value="<?= Html::e($fValue) ?>" <?= $fAttrs ?>>
    <?php endif; ?>

    <?php if (!empty($f['hint'])): ?>
        <p class="form-hint"><?= Html::e($f['hint']) ?></p>
    <?php endif; ?>
    <?php if ($fError): ?>
        <p class="form-error" id="err-<?= $fName ?>"><?= Html::e($fError) ?></p>
    <?php endif; ?>
</div>

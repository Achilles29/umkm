<label class="staff-list-size">Baris per halaman
    <select name="per_page" aria-label="Jumlah baris per halaman">
        <?php foreach (array(10,20,50,100) as $size): ?>
        <option value="<?= $size ?>" <?= (int)$per_page===$size?'selected':'' ?>><?= $size ?></option>
        <?php endforeach; ?>
    </select>
</label>

<?php if (!empty($kpi_cards)): ?>
    <?php foreach ($kpi_cards as $card): ?>
        <div class="col-xxxl-4 col-xl-4 col-lg-6 col-md-6 col-12">
            <div class="box mb-20">
                <div class="box-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-15">
                        <div class="d-flex align-items-center gap-15">
                            <?php if (!empty($card['icon'])): ?>
                                <img src="<?= img($card['icon']); ?>" alt="" class="w-120"/>
                            <?php endif; ?>
                            <div>
                                <h4 class="mb-5 text-muted text-uppercase fs-12 letter-spacing-1"><?= htmlspecialchars($card['title']); ?></h4>
                                <h2 class="mb-0 fw-600">
                                    <?= is_numeric($card['value']) ? number_format((float) $card['value']) : htmlspecialchars((string) $card['value']); ?>
                                </h2>
                                <?php if (!empty($card['description'])): ?>
                                    <p class="mb-0 text-muted fs-12"><?= htmlspecialchars($card['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($card['tag'])): ?>
                            <span class="badge bg-light text-primary fw-500 px-3 py-2"><?= htmlspecialchars($card['tag']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
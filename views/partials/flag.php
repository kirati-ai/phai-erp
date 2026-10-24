<?php
/**
 * A small flag drawn in SVG (Windows shows no flag emoji): $lang 'th' = Thailand, 'en' = United States.
 * @var string $lang
 */
if ($lang === 'th'): ?>
<svg class="phai-flag" viewBox="0 0 9 6" aria-hidden="true"><rect width="9" height="6" fill="#a51931"/><rect y="1" width="9" height="4" fill="#f4f5f8"/><rect y="2" width="9" height="2" fill="#2d2a4a"/></svg>
<?php else: ?>
<svg class="phai-flag" viewBox="0 0 19 10" aria-hidden="true"><rect width="19" height="10" fill="#b22234"/><path d="M0 1.154h19M0 2.692h19M0 4.231h19M0 5.769h19M0 7.308h19M0 8.846h19" stroke="#fff" stroke-width=".77"/><rect width="7.6" height="5.385" fill="#3c3b6e"/><g fill="#fff"><?php
    for ($r = 0; $r < 9; $r++) {
        $even = $r % 2 === 0;
        for ($c = 0; $c < ($even ? 6 : 5); $c++) {
            printf('<circle cx="%.3f" cy="%.3f" r=".22"/>', ($even ? .633 : 1.267) + $c * 1.267, .3 + $r * .598);
        }
    }
?></g></svg>
<?php endif;
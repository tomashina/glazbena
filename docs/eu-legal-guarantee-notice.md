# EU legal guarantee notice

The footer uses the same unmodified official European Commission notice as the Libermedia reference implementation.

- Asset: `catalog/view/theme/basel/image/legal-guarantee/legal-guarantee-notice-hr.svg`
- Source package: <https://commission.europa.eu/document/download/27c45f1f-78a1-47a7-a7cc-adf23afee5ea_en?filename=SVG.zip>
- Original package member: `Legal guarantee_notice HR.svg`
- SHA-256: `a22432ff287ae772fd7f1bc7198dce26cbcfba3b1c8c6fa207189c1163e2612e`
- Information URL: <https://europa.eu/youreurope/jamstva_hr>

Do not edit, recolor, crop, re-typeset, or change the proportions of the official asset.

The Basel footer displays “Zakonsko jamstvo – najmanje 2 godine” (localized for English) directly below the Certified Shop badge, inside the footer. The default fallback displays the same control inside its footer. Selecting the link opens the complete Croatian notice in the existing Bootstrap modal, with keyboard scrolling, a full-size notice link, and a link to the official information page. Without JavaScript, the footer link opens the official information page directly.

This is the statutory guarantee notice from the reference implementation; it does not create a separate commercial warranty or promise two extra years.

After uploading the changed source files, refresh OpenCart modifications and the theme/template cache so generated copies of `catalog/controller/common/footer.php` use the new footer data and stylesheet. The digital price list footer link is displayed only while the anchor-price module is enabled.

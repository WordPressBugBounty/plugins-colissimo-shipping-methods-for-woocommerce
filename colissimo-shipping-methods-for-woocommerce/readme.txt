=== Colissimo shipping methods for WooCommerce ===
Contributors: iscpcolissimo
Tags: shipping, colissimo, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 3.2.1
Requires PHP: 7.4
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

This plugin allows you to use Colissimo shipping methods in WooCommerce

== Description ==

> #### Requirements
> [WooCommerce (Tested regularly on its latest version)](https://wordpress.org/plugins/woocommerce/)

This plugin allows:
* The integration of the display of pickup points on the merchant's site
* The generation and printing of labels from the WooCommerce Colissimo back office
* The tracking of shipments to recipients

= Characteristics : =

Colissimo contains several essential features in a single plugin.

It allows:

* Front-office:
	- Responsive design display of pickup points
	- Order tracking from the merchant's site
	- Simplification of the return process, including the possibility of making the return in a mailbox
* Back-office:
	- Shipping parcels to France, Overseas and internationally
	- Labels creation from the merchant's back office
	- Generating a deposit slip
	- Tracking orders

= Benefits for the e-merchant =

The Colissimo plugin is a complete & free solution that will save you time on a daily basis in processing your orders and tracking your shipments. You can easily develop your sales abroad by offering the innovative services of the Colissimo range.
If needed, you can rely on Colissimo technical support.

= Benefits for the recipient =

Colissimo also makes the recipient's life easier by offering them the widest range of delivery solutions (in France and internationally).
The recipient can follow the journey of their parcel on their customer area and make returns from this same area if they wish.


== Screenshots ==
1. Colissimo orders listing
2. WooCommerce orders listing
3. Plugin settings
4. Colissimo pickup with widget
5. Shipping prices preview
6. Activate shipping methods on zones


== Changelog ==

= 3.2.1 =

SÉCURITÉ

- Une protection a été ajoutée afin de corriger une faille XSS via l'adresse de livraison. Page officielle lorsqu'elle sera publiée : https://www.cve.org/CVERecord?id=CVE-2026-129028

AMÉLIORATIONS

- Les retours sont maintenant autorisés pour la Bulgarie, Chypre et l'Islande
- Le nombre d'étiquettes de retour pouvant être générées par commande est maintenant limité au nombre de produits physiques

CORRECTIFS

- L'adresse par défaut du client n'est plus écrasée par l'adresse du point de retrait sélectionné lors d'un achat en point de retrait
- Dans le cas où un système de cache est appliqué à la session de WooCommerce, le point de retrait sélectionné est maintenant correctement sauvegardé et utilisé lors de la validation d'une commande
- La seconde ligne d'adresse pour une expédition aux USA est à présent correctement visible sur l'étiquette d'expédition

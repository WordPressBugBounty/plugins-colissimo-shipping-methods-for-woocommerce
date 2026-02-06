=== Colissimo Officiel : Méthodes de livraison pour WooCommerce ===
Contributors: iscpcolissimo
Tags: shipping, colissimo, woocommerce
Requires at least: 4.7
Tested up to: 6.8
Stable tag: 2.8.2
Requires PHP: 7.4.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Ce plugin permet d'utiliser les méthodes de livraison Colissimo dans WooCommerce

== Description ==

> #### Requirements
> [WooCommerce (Testé régulièrement sur sa dernière version)](https://wordpress.org/plugins/woocommerce/)

Ce plugin permet :
* L’intégration de l’affichage des points retrait sur le site marchand
* La génération et l’impression des étiquettes depuis le B.O. WooCommerce Colissimo
* Le suivi des expéditions aux destinataires

= Caractéristiques : =

Colissimo Officiel regroupe plusieurs fonctionnalités essentielles dans un seul plugin.

Celui-ci permet :
 
* EN FRONT OFFICE :
    - L’affichage en responsive design des points de retrait
    - Le suivi de commande depuis le site marchand
    - La simplification du process retour dont la possibilité d’effectuer le retour en boite aux lettres
* EN BACK OFFICE :
    - L’envoi de colis vers la France, l’Outre Mer et l’international
	- L’édition d’étiquettes depuis le back office marchand
	- La génération d’un bordereau de dépôt
	- Le suivi des commandes 

= Bénéfices pour le e-commerçant : =

Le plugin Colissimo-Officiel est une solution complète & gratuite qui vous permettra de gagner du temps au quotidien dans le traitement de vos commandes et le suivi de vos expéditions. Vous pourrez facilement développer vos ventes à l’export en proposant les services innovants de la gamme Colissimo.
En cas de besoin, vous pourrez vous appuyer sur le support technique Colissimo .

= Bénéfices pour le e-acheteur : =

Colissimo facilite également la vie du destinataire en lui proposant le plus large éventail de solutions de livraison (en France et à l’international).
L’e-acheteur peut suivre sur son espace client le parcours de son colis et effectuer les retours depuis ce même espace s’il le souhaite.


== Screenshots ==
1. Onglet commandes Colissimo
2. Onglet commandes WooCommerce
3. Paramétrage du plugin
4. Point de retrait Colissimo avec widget
5. Prévisualisation des frais de livraison
6. Associer les transporteurs à des zones


== Changelog ==

= 2.8.2 =

CORRECTIFS

* Le téléchargement et l'impression des bordereaux de dépôt ont été corrigés lorsque le mode de connexion utilise une clé d'application


= 2.8.1 =

CORRECTIFS

* L'encodage des images de l'extension a été corrigé


= 2.8.0 =

FONCTIONNALITÉS

* Vous pouvez à présent catégoriser vos produits et catégories de produit pour les matières dangereuses si l'option est activée sur votre contrat Colissimo
* Une fonctionnalité permettant l'affichage d'une estimation de la date de livraison dans le tunnel de commande a été ajoutée
* De nouvelles options ont été ajoutées relatives aux livraisons en destination des USA

AMÉLIORATIONS

* Les options relatives au tunnel de commande ont été regroupées dans une seule section des réglages
* La structure des fichiers de l'extension a été revue afin d'apporter une meilleure sécurité et stabilité
* Les numéros de téléphone sont à présent gérés même si leur format n'est pas standard (nettoyage des espaces, points, tirets, etc...)

CORRECTIFS

* Le numéro de téléphone est à présent obligatoire pour les achats en mode express afin de ne plus bloquer les livraisons en point de retrait
* Le point de retrait le plus proche est désormais sélectionné automatiquement lors d'un achat en mode express
* Le bouton "Effectuer un retour" a été supprimé de la page de confirmation de commande
* La recherche du widget Colissimo pour les points de retrait n'est plus bloquée si l'adresse de livraison n'est pas renseignée avant ouverture
* Des préparatifs à PHP 8.5 ont été effectués, supprimant par la même occasion certains messages dans les logs serveur
* Correction de la détection de l'emballage avancé concernant les dimensions de l'emballage


= 2.7.1 =

AMÉLIORATIONS

* Les expéditions vers les États-Unis sans DDP ont été réactivées


= 2.7.0 =

FONCTIONNALITÉS

* Il est à présent possible de choisir entre le service postal local et le transporteur partenaire pour les livraisons avec signature en Belgique
* Une traduction de la documentation en anglais a été ajoutée

AMÉLIORATIONS

* Une nouvelle section dédiée au tunnel de commande a été ajoutée dans les réglages
* Les informations affichées sur la page de suivi ne sont plus systématiquement en français, mais dans la langue du pays de livraison lorsque disponible ou en anglais sinon
* Les expéditions vers les États-Unis ont été désactivées lorsque le DDP n'est pas utilisé (il vous est aussi à présent obligatoire de renseigner le code MID dans la description de chaque produit pour que les douanes américaines ne refusent pas le colis)
* Certains appels aux APIs de Colissimo ont été optimisés afin d'améliorer les performances en réduisant le nombre d'appels

CORRECTIFS

* La génération automatique des étiquettes a été corrigées pour les commandes en point de retrait lorsque la mise à jour automatique des statuts de livraison est activée
* Les informations du point de retrait sélectionné sont à présent enregistrées au niveau de la commande afin de mieux gérer des cas spécifiques liés à certaines méthodes de paiement comme Monetico, Up2Pay, Apple pay et PayPal


= 2.6.1 =

CORRECTIFS

* Le filtre par catégorie de produit sur les tranches de prix est à présent fonctionnel pour les produits variables


= 2.6.0 =

FONCTIONNALITÉS

* Il est à présent possible de conditionner les tranches de prix selon la catégorie des articles du panier
* Un récapitulatif des options du compte Colissimo a été ajouté dans les réglages généraux
* La compatibilité avec la méthode de paiement Up2Pay a été ajoutée pour les envois en point de retrait
* La compatibilité avec Yith bundles a été ajoutée pour les paniers comportant au moins un bundle de produits
* Une documentation complète a été rédigée, téléchargeable dans la partie support des réglages

AMÉLIORATIONS

* L'option d'affichage des points de retrait à l'international a été retirée, étant devenue obsolète
* Le fichier de logs a été déplacé dans le dossier d'uploads de WordPress afin de pouvoir fonctionner avec certaines configurations serveur
* Le point de retrait le plus proche est à présent automatiquement utilisé lors de la génération d'une étiquette lorsqu'un achat via Apple Pay express est effectué. Cette méthode de paiement en mode express redéfinissait la méthode de livraison, écrasant le point de retrait sélectionné par le client.

CORRECTIFS

* Des modifications ont été apportées sur la carte des points de retrait afin de gérer correctement l'affichage du widget Colissimo avec le bloc Gutenberg de checkout de WooCommerce
* Les codes postaux comportant un tiret sont à présent gérés correctement lorsqu'une étiquette est générée
* Pour les impressions thermiques, l'option de sélection du protocole HTTP/HTTPS a été corrigée, elle n'avait précédemment aucun effet.

CORRECTIFS

* L'affichage des points de retrait pour Google Maps et Leaflet est à présent fonctionnel lorsque la connexion au compte Colissimo se fait avec une clé d'application


= 2.5.2 =

CORRECTIFS

* L'affichage des points de retrait pour Google Maps et Leaflet est à présent fonctionnel lorsque la connexion au compte Colissimo se fait avec une clé d'application


= 2.5.1 =

CORRECTIFS

* Une erreur a été corrigée lors de la validation d'une commande passée en point de retrait empêchant le remplacement de l'adresse du point sélectionné


= 2.5.0 =

FONCTIONNALITÉS

* L'option de filtre des types de point de retrait a été remplacée au profit d'une option plus complète
* L'option FTD pour les DOM-TOM a été transformée en DDP, et une option supplémentaire a été ajoutée pour permettre la configuration d'un surcoût pour les DOM-TOM

AMÉLIORATIONS

* La compatibilité avec la la nouvelle méthode de gestion de la TVA a été ajoutée pour WooCommerce 9.7.0+
* La compatibilité avec le plugin Chronopost a été ajoutée, ce dernier n'empêche plus le plugin Colissimo d'être activé
* La compatibilité avec le nouvel éditeur d'emails avancé de WooCommerce a été ajoutée pour les emails Colissimo
* De meilleures indications ont été ajoutées pour l'import des étiquettes depuis le listing des commandes Colissimo
* Lorsque les logos transporteurs sont activés, le logo du partenaire local du pays de livraison est à présent affiché
* Un lien a été ajouté dans les réglages pour vous permettre de vous rendre sur la page du matériel d'emballage Colissimo
* Un formulaire de satisfation a été ajouté dans les réglages pour vous permettre de nous faire part de vos retours sur le module

CORRECTIFS

* Une erreur a été corrigée sur le système de gestion d'erreur intervenant lors de la génération par un client d'une étiquette de retour
* Le texte "Note" dans l'email de suivi est à présent correctement traduit lorsqu'il est envoyé en anglais
* Une sécurité a été ajoutée sur l'import des étiquettes depuis le listing des commandes Colissimo, lorsque le contenu importé est incorrect


= 2.4.0 =

AMÉLIORATIONS

* Les tarifs par défaut ont été mis à jour pour prendre ceux de 2025
* La compatibilité avec PHP 8.4 a été ajoutée
* L'extension est désormais compatible avec certaines extensions changeant le tunnel de commande en plusieurs étapes pour l'envoi en point de retrait
* La mise à jour des statuts de livraison a été revue afin d'améliorer les performances
* Ajout de la compatibilité avec une extension modifiant les fréquences des tâches planifiées de WordPress pour la mise à jour des statuts de livraison

CORRECTIFS

* L'enregistrement du point de retrait sélectionné a été améliorée pour certains cas spécifiques
* Le nombre de jours par défaut n'était pas toujours bien sauvegardé pour la mise à jour automatique des statuts de livraison


= 2.3.0 =

FONCTIONNALITÉS

* Un nouveau bloc pour le suivi a été ajouté sur la page d'une commande dans le compte client
* La colonne de suivi sur le listing des commandes client dépend désormais d'une option

AMÉLIORATIONS

* La carte des points de retrait avec Google Maps a été mise à jour afin d'être compatible avec leurs dernières fonctionnalités
* Les textes relatifs à l'identifiant Colissimo ont été changés
* Les options des réglages dépendant d'autres options s'affichent désormais uniquement lorsque nécessaire
* La génération d'étiquettes retour par l'admin n'est plus possible lorsque le retour sécurisé est activé

CORRECTIFS

* Le chargement des traductions en français a été corrigé pour WordPress 6.7
* Correction de certains cas où le point de retrait n'était pas bien sauvegardé lors d'une commande


= 2.2.0 =

FONCTIONNALITÉS

* Une nouvelle option vous permettant d'activer le retour sécurisé pour les étiquettes générées par vos clients a été ajoutée
* La compatibilité avec l'extension WPC Product Bundles for WooCommerce a été ajoutée

AMÉLIORATIONS

* Les performances ont été améliorées pour les sites WordPress multisite
* Les performances ont été améliorées globalement sur toutes les pages Colissimo
* Le nom de la société est à présent ajouté dans la facture générée
* Les commandes en brouillon sont maintenant cachées par défaut dans le listing Colissimo

CORRECTIFS

* Les emails de notification lorsqu'une étiquette est générée sont à présent envoyés dans la bonne langue lorsque WPML est installé
* La purge des anciennes étiquettes a été corrigée pour les sites n'ayant pas l'option HPOS de WooCommerce activée
* La livraison aux Emirats Arabes Unis a été corrigée lorsqu'aucun code postal n'est renseigné
* L'expédition avec signature option DDP a été corrigée pour les pays autres que le Royaume-Uni pour les commandes de moins de 160€


= 2.1.0 =

FONCTIONNALITÉS

* Il est à présent possible de s'autentifier avec une clé de connexion Colissimo
* La fonctionnalité de code sécurisé à la livraison est maintenant paramétrable par commande ou globalement en se basant sur le prix de commande
* Des liens ont été ajoutés dans les réglages pour vous rendre sur la Colissimo Box et la page de gestion de vos services Colissimo

AMÉLIORATIONS

* Amélioration des performances lors du chargement du listing des commandes Colissimo
* Le retour des colis pour la Norvège a été activé
* Il n'est plus nécessaire d'avoir un numéro français pour les envois en point de retrait en France
* La compatibilité avec Yith WooCommerce Multi Vendor a été améliorée pour les commandes en point de retrait
* La plupart des appels d'API via SOAP ont été remplacés, et une meilleure gestion des erreurs a été ajoutée lorsque SOAP n'est pas disponible

CORRECTIFS

* Le téléchargement des étiquettes de retour depuis un compte utilisateur a été corrigé
* Les étiquettes de retour téléchargées depuis un compte utilisateur sont à présent du PDF lorsque le format choisi dans les réglages est ZPL ou DPL
* Lors d'une livraison en point de retrait, les numéros comportant des espaces sont maintenant pris en compte correctement
* L'envoi de documents douaniers a été corrigé lorsque le HPOS de WooCommerce est activé


= 2.0.1 =

CORRECTIFS

* Correction d'une erreur PHP sur le listing des commandes Colissimo


= 2.0.0 =

FONCTIONNALITÉS

* Il est à présent possible de différencier les produits sans classe de livraison lors du paramétrage les tarifs de livraison
* Les tarifs Colissimo peuvent à présent être importés facilement pour chaque méthode à l'aide d'un bouton
* Une option a été ajoutée pour vous permettre de choisir entre le prix le moins cher ou le plus cher, lorsque plusieurs lignes de votre grille de prix correspondent au panier (la moins chère par défaut)
* Une nouvelle section dans les réglages relative aux colis a été ajoutée. Vous pouvez y définir plusieurs types d'emballage suivant le contenu du panier
* Une option a été ajoutée, vous permettant de donner le choix au client de générer une étiquette de retour pour certains produits en particulier au lieu de tous
* Une nouvelle option vous permet de paramétrer le nombre de jours pendant lesquels vos clients ont la possibilité de générer une étiquette de retours (14 jours par défaut)

AMÉLIORATIONS

* Les codes postaux des États-Unis contenant un tiret sont maintenant acceptés
* La dépendance à WooCommerce a été ajoutée pour se servir du nouveau système de dépendance de WordPress 6.5
* Vous pouvez maintenant paramétrer le nombre de jours durant lesquels l'extension rafraîchit le statut de livraison pour les colis en cours de livraison
* Les icones de téléchargement et d'impression ont été ajoutées dans le message de confirmation de création d'une étiquette
* L'option de nettoyage des vielles étiquettes est à présent activée par défaut afin d'éviter une surcharge de la base de données
* La méthode de livraison Colissimo Internationale à été dépréciée au profit de l'envoi avec signature (étant la même méthode côté Colissimo)
* Un formulaire a été ajouté dans les réglages afin de vous permettre de nous fournir un retour sur l'extension

CORRECTIFS

* Une sécurité a été ajoutée afin d'éviter un problème d'affichage sur la récupération du statut de livraison
* Lors d'une livraison en point de retrait pour un panier de plus de 20kg, les points non éligibles ne sont plus proposés
* Le listing des bordereaux a été revu pour corriger un problème où certaines pages étaient vides
* L'intégration avec le bloc Gutenberg de WooCommerce a été revue afin de corriger un problème d'appels multiples en tâche de fond (performances)
* La vérification sur le point de retrait sélectionné a été corrigée pour le bloc Gutenberg WooCommerce lorsque le client sélectionne point de retrait, puis une autre méthode, puis point de retrait à nouveau
* Dans certains cas, les informations du point de retrait sélectionné n'étaient pas sauvegardées. Une sécurité a été ajoutée pour éviter ce problème


= 1.9.5 =

CORRECTIFS

* La vérification des identifiants Colissimo dans les réglages et l'affichage du widget Colissimo ont été corrigés


= 1.9.4 =

AMÉLIORATIONS

* La durée de vie des logs Colissimo a été changée à 14 jours
* Un CSS spécial ajouté pour éviter les cas où un élément invisible recouvre le bouton de sélection des points de retrait
* Optimisation du chargement des scripts de la carte des points de retrait

CORRECTIFS

* Le pré-remplissage de l'adresse dans la carte des points de retrait a été corrigé pour l'ancien tunnel de commande, et ajouté pour le nouveau bloc Gutenberg de WooCommerce
* Correction de la valdation d'achat pour le nouveau bloc Gutenberg si la méthode sélectionnée n'est pas en point de retrait
* Correction d'un warning "silencieux" lors de la génération d'une étiquette depuis le listing des commandes Colissimo


= 1.9.3 =

FONCTIONNALITÉS

* Une option a été ajoutée pour permettre de prendre en compte le prix des produits sans livraison dans le calcul des frais de port

AMÉLIORATIONS

* Les produits virtuels ou n'ayant pas besoin de livraison ne sont plus affichés dans le bandeau de génération d'étiquette et la déclaration de douanes
* Le lien direct d'édition d'une commande est maintenant utilisé sur le listing Colissimo à la place du lien WordPress de base (pour le HPOS)

CORRECTIFS

* Un message d'erreur a été corrigé sur le bloc Gutenberg de panier de WooCommerce


= 1.9.2 =

FONCTIONNALITÉS

* Vous pouvez maintenant télécharger les logs Colissimo depuis la configuration de l'extension
* Une option a été ajoutée pour permettre aux clients d'indiquer des instructions de livraison sur l'étiquette
* La compatibilité avec les sites WordPress multisite a été ajoutée

AMÉLIORATIONS

* Le mot de passe Colissimo rentré dans les réglages est maintenant encodé dans la base de données pour plus de sécurité
* L'apostrophe française est automatiquement remplacée afin d'éviter un problème de compatibilité avec la carte Colissimo pour la sélection d'un point de retrait
* L'extension est à présent compatible avec le nouveau bloc Gutenberg de WooCommerce de tunnel de commande, pour la sélection des points de retrait
* L'extension a une meilleure compatibilité avec les sites dont certains dossiers ne sont pas ouverts en écriture
* Une sécurité a été ajoutée pour gérer les serveurs n'ayant pas activé l'extension PHP permettant la création de fichiers ZIP
* Le téléchargement des étiquettes n'ouvre plus d'onglet temporaire dans le navigateur
* L'affichage des options des méthodes de livraison a été revu pour les interfaces de WooCommerce 8.4

CORRECTIFS

* Un correctif a été apporté pour le listing des commandes Colissimo lorsque le HPOS est activé et que la synchro des commandes est désactivée dans WooCommerce
* La redirection après une suppression manuelle d'une étiquette a été corrigée lorsque le HPOS est activé et que la synchro des commandes est désactivée dans WooCommerce
* Les conditions "Prix maximum" et "Poids maximum" sont à présent correctement exportées lors de l'export des tarifs d'envoi
* La compatibilité avec d'anciennes versions de WooCommerce a été restaurée
* Certaines traductions manquantes ont été corrigées
* Le bon code pays est à présent utilisé pour les étiquettes de retour (le pays de l'adresse d'origine était utilisé dans certains cas)
* Les accents dans les noms de produits sont à présent correctement remplacés lors de la génération de la déclaration de douanes


= 1.9.1 =

AMÉLIORATIONS

* Les prix par défaut installés à la première activation ont été mis à jour pour suivre la grille tarifaire de 2023
* Les codes courts {order_date} et {order_number} sont à présent disponibles dans le sujet des emails de suivi Colissimo
* La génération d'une étiquette depuis la page d'édition d'une commande a été revue pour le HPOS
* La librairie TCPDF utilisée pour la génération des factures a été mise à jour
* Un message informatif a été ajouté pour les comptes Facilité n'ayant pas encore accepté les CGV
* De multiples sécurités ont été ajoutées suites aux modifications pour le HPOS afin de s'assurer que la commande existe bien lors de diverses manipulations

CORRECTIFS

* La redirection après suppression d'un bordereau depuis la page d'édition d'une commande a été corrigée
* L'application d'une méthode de livraison Colissimo depuis la page d'édition d'une commande a été corrigé
* Une sécurité a été ajoutée lors de la mise à jour des données relatives aux bordereaux pour les versions antérieures à la v1.8.2
* Correction de la mise à jour des statuts Colissimo pour les commandes avec numéros de suivi importés


= 1.9.0 =

FONCTIONNALITÉS

* L'extension a été intégralement revue pour être compatible avec l'option COT (HPOS) de WooCommerce

AMÉLIORATIONS

* La ligne de l'adresse est à présent automatiquement séparée en deux lignes lorsque la valeur dépasse la longueur maximum gérée par Colissimo, lorsque c'est possible

CORRECTIFS

* Les factures n'ont plus de problème à se générer lorsqu'une image est présente dans le nom de la méthode de paiement


= 1.8.2 =

FONCTIONNALITÉS

* Une documentation pour développeurs a été ajoutée dans les réglages du plugin
* Une option a été ajoutée dans les réglages pour permettre de mettre en place un surcoût global aux livraisons
* Le bordereau de fin de journée devient le bordereau de fin de période, et une option a été ajoutée pour permettre de choisir le nombre de jours concernés
* Un filtre par date a été ajouté aux filtres du listing des commandes faites avec Colissimo
* Une option a été ajoutée dans le bandeau Colissimo pour permettre de changer la méthode de livraison d'une commande pour une méthode Colissimo
* Il est à présent possible de modifier le point de retrait d'une commande depuis le bandeau Colissimo
* La livraison en DDP a été ajoutée pour l'Australie
* Une option a été ajoutée pour permettre de spécifier le nombre d'exemplaires de la CN23 voulu
* Un bouton a été ajouté sur la popup de sélection des points de retrait pour permettre aux clients d'afficher plus de points
* Une option a été ajoutée pour ajouter ou non la facture lors de l'impression d'une étiquette
* Une section contenant des tutoriels vidéo a été ajoutée dans les réglages du plugin

AMÉLIORATIONS

* Une page de création de bordereau et une page d'historique des bordereaux ont été ajoutées dans le menu Colissimo
* Une section "Accueil" a été ajoutée dans les réglages du plugin pour accompagner les nouveaux utilisateurs
* Les options de livraison gratuite ne forcent plus l'affichage des méthodes d'envoi si aucune ligne de la grille de prix ne correspond au panier
* Une sécurité a été ajoutée sur l'option de nettoyage des étiquettes pour la réactiver si son déclencheur s'est désactivé
* Si une erreur survient lors de l'impression d'étiquettes en imprimante thermique, le message d'erreur est à présent affiché directement plutôt que de rediriger vers la console du navigateur

CORRECTIFS

* La compatibilité avec certaines configurations serveur a été ajoutée (au niveau de l'inclusion de fichiers)
* La carte de points de retrait s'affiche à présent correctement lorsque sa popup est fermée puis réouverte
* Le format d'étiquette en retour à l'international est à présent forcé au format PDF même si un autre format est sélectionné dans les réglages, car c'est le seul format disponible pour le retour avec cette méthode
* La librairie de gestion des PDF ne tente plus de charger sa configuration dans des dossiers inaccessibles
* La fréquence de notre tâche cron WordPress passe de 15 secondes à 15 minutes


= 1.8.1 =

FONCTIONNALITÉS

* La compatibilité avec WPML a été ajoutée pour les filtres sur les classes de livraison
* Une nouvelle option vous permet de baser les frais de livraison sur le prix hors taxe des produits

AMÉLIORATIONS

* Mise à jour du numéro de version de WooCommerce testé
* Ajout d'une sécurité lors de l'export des tarifs d'envoi
* La popup de la carte des points de retrait a été revue pour éviter les incompatibilités avec certains thèmes (aucun changement visuel apparent)
* Un message informatif a été ajouté dans les réglages lorsque le thème DIVI est actif et que son option modifiant jQuery est activée
* Les filtres WooCommerce sont à présent conservés après la génération d'une étiquette sur le listing des commandes WooCommerce
* Le retour des colis a été activé pour le Danemark et la Suède
* Le format du code postal est à présent automatiquement corrigé pour le Luxembourg lors de la génération d'une étiquette, lorsque le préfixe "L-" est présent

CORRECTIFS

* Les emojis dans les noms de produits ne bloquent plus la génération de la déclaration de douanes
* Une sécurité a été ajoutée lors de la génération d'une étiquette lorsqu'un des produits achetés a été supprimé de la boutique
* Une sécurité a été ajoutée sur le bandeau Colissimo lorsque WooCommerce échoue à charger la méthode de livraison
* Si un numéro de téléphone de livraison est renseigné, alors il est utilisé pour la génération de l'étiquette plutôt que le numéro de téléphone de facturation


= 1.8.0 =

FONCTIONNALITÉS

* Une option a été ajoutée pour permettre de limiter le nombre de points de retrait affichés sur la carte
* Une option a été ajoutée pour permettre de n'afficher que certains types de points de retrait sur la carte
* Une option a été ajoutée pour permettre de n'afficher que la liste des points de retrait en version mobile, sans la carte
* La livraison vers Saint-Martin est désormais disponible avec et sans signature

AMÉLIORATIONS

* Un nouveau hook a été ajouté pour permettre la personnalisation des PDF téléchargés
* Mise à jour de la dernière version testée de WooCommerce
* La version minimale de PHP a été relevée à 7.4.0
* Un message d'avertissement a été ajouté dans les réglages lorsque le poids de l'emballage est anormalement élevé
* Un message d'avertissement a été ajouté sur le listing des commandes Colissimo lorsque le système de cron de WordPress est désactivé
* L'expédition depuis Andorre a été activée
* La confirmation de sélection d'un point de retrait a été supprimée
* Les identifiants sont à présent vérifiés lorsqu'ils sont entrés ou modifiés dans les réglages et de meilleurs instructions sont ajoutées
* Le système de popup contenant la carte des points de retrait a été amélioré afin d'éviter des problèmes d'incompatibilité avec d'autres plugins/thèmes

CORRECTIFS

* Correction du téléchargement des étiquettes lorsque mod_deflate est activé sur le serveur
* La méthode de points de retrait n'est plus proposée aux clients si les identifiants dans les réglages sont incorrects ou manquants

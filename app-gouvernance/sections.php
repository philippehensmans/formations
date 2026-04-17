<?php
/**
 * Définition des sections et questions de l'évaluation de gouvernance.
 * Retourne un tableau utilisé par app.php et view.php.
 */

return [
    [
        'id' => 'structures_org',
        'title' => 'Structures organisationnelles',
        'subsections' => [
            [
                'id' => 'assemblees_generales',
                'title' => '1. Les assemblées générales',
                'questions' => [
                    ['id' => 'ag_procedures', 'text' => 'Quelles procédures avez-vous mises en place pour vos assemblées générales avec vos membres ?', 'type' => 'scale'],
                    ['id' => 'ag_info_membres', 'text' => "Le CA informe-t-il par écrit tous·tes les membres, dans un délai adéquat, de la tenue de l'assemblée générale (AG) et de la procédure de vote ?", 'type' => 'scale'],
                    ['id' => 'ag_compte_rendu', 'text' => "Dans quelle mesure et à quelle fréquence le CA rend-il compte de la stratégie, de l'impact, des finances et des décisions aux membres avant l'assemblée générale ?", 'type' => 'scale'],
                    ['id' => 'ag_procedures_definies', 'text' => 'Dans quelle mesure les procédures permettant de devenir membre et de convoquer une assemblée générale sont-elles définies dans la charte et le règlement ?', 'type' => 'scale'],
                    ['id' => 'ag_questions_membres', 'text' => "Dans quelle mesure les membres ont-ils le temps et l'espace nécessaires pour adresser des questions et des requêtes au CA ?", 'type' => 'scale'],
                    ['id' => 'ag_reunions_urgence', 'text' => "Des réunions d'urgence ont-elles été convoquées par le CA au cours des deux dernières années ?", 'type' => 'boolean'],
                    ['id' => 'ag_dispositions_urgence', 'text' => "Y a-t-il dans votre organisation des dispositions particulières pour convoquer une réunion d'urgence/extraordinaire ?", 'type' => 'boolean'],
                ],
            ],
            [
                'id' => 'conseil_administration',
                'title' => "2. Conseil d'administration démocratique et responsable",
                'questions' => [
                    ['id' => 'ca_comite_candidatures', 'text' => "Un comité indépendant chargé des candidatures, et/ou procédures écrites et claires permettant d'évaluer les compétences des candidat·e·s", 'type' => 'scale'],
                    ['id' => 'ca_procedure_candidature', 'text' => 'Procédure de candidature écrite, claire et équitable, prévoyant un délai suffisant pour les nominations', 'type' => 'scale'],
                    ['id' => 'ca_procedure_transparente', 'text' => 'Procédure transparente et accessible au public concernant le recrutement, les nominations et les élections', 'type' => 'scale'],
                    ['id' => 'ca_description_roles', 'text' => 'Description de tous les rôles du CA/des comités', 'type' => 'scale'],
                    ['id' => 'ca_rgpd', 'text' => "Politique d'utilisation et de traitement des données personnelles, conforme au RGPD", 'type' => 'scale'],
                    ['id' => 'ca_departage', 'text' => 'Procédure écrite pour départager les candidat·e·s ayant obtenu le même nombre de voix', 'type' => 'scale'],
                    ['id' => 'ca_postes_vacants', 'text' => 'Procédure écrite pour pourvoir les postes devenus vacants entre deux élections', 'type' => 'scale'],
                    ['id' => 'ca_conflits_interets', 'text' => 'Dans quelle mesure votre organisation exclut-elle les membres du personnel des postes élus au sein du CA ?', 'type' => 'scale'],
                ],
            ],
            [
                'id' => 'fonctions_responsabilites',
                'title' => '3. Fonctions et responsabilités claires et durables',
                'questions' => [
                    ['id' => 'fr_definitions_claires', 'text' => "Dans quelle mesure les fonctions et responsabilités de l'assemblée générale, du CA et du directeur/de la directrice sont-elles clairement définies ?", 'type' => 'scale'],
                ],
            ],
        ],
    ],
    [
        'id' => 'finances_croissance',
        'title' => 'Finances et croissance',
        'subsections' => [
            [
                'id' => 'controles_financiers',
                'title' => 'Contrôles financiers',
                'questions' => [
                    ['id' => 'fin_budgets_annuels', 'text' => "Élaboration de budgets annuels approuvés par le CA et de prévisions financières pluriannuelles", 'type' => 'scale'],
                    ['id' => 'fin_comptes_gestion', 'text' => 'Présentation régulière au CA de comptes de gestion indiquant la situation à date', 'type' => 'scale'],
                    ['id' => 'fin_politique_ethique', 'text' => "Politique éthique d'investissement et d'achat conforme aux décisions en matière d'investissements éthiques", 'type' => 'scale'],
                    ['id' => 'fin_mesures_controle', 'text' => 'Mesures de contrôle financier permettant de garantir la bonne gestion et de prévenir la fraude', 'type' => 'scale'],
                    ['id' => 'fin_comptes_certifies', 'text' => 'Établissement annuel des comptes certifiés par un commissaire aux comptes', 'type' => 'scale'],
                    ['id' => 'fin_approbation_ca', 'text' => 'Le CA approuve et signe les comptes annuels et examine la lettre annuelle de certification', 'type' => 'boolean'],
                    ['id' => 'fin_registre_suivi', 'text' => 'Registre de suivi efficace tenu par la direction et le CA quant aux actions conseillées', 'type' => 'scale'],
                    ['id' => 'fin_verification_independante', 'text' => "Vérification et confirmation indépendantes de l'efficacité des systèmes de contrôle interne", 'type' => 'scale'],
                ],
            ],
            [
                'id' => 'financement_durable',
                'title' => 'Financement durable et diversification des revenus',
                'questions' => [
                    ['id' => 'fd_accroitre_sympathisants', 'text' => "L'organisation dispose d'activités précises visant à accroître le nombre de sympathisant·e·s", 'type' => 'scale'],
                    ['id' => 'fd_fideliser_sympathisants', 'text' => "L'organisation dispose d'activités précises visant à fidéliser les sympathisant·e·s", 'type' => 'scale'],
                    ['id' => 'fd_encourager_adhesion', 'text' => "L'organisation dispose d'activités précises visant à encourager les sympathisant·e·s à devenir membres", 'type' => 'scale'],
                    ['id' => 'fd_diversifier_revenus', 'text' => "L'organisation dispose d'activités précises visant à diversifier les sources de revenus", 'type' => 'scale'],
                    ['id' => 'fd_mecanismes_evaluation', 'text' => "L'organisation dispose de mécanismes clairs lui permettant d'évaluer sa performance en matière de collecte de fonds", 'type' => 'scale'],
                    ['id' => 'fd_formations_ressources', 'text' => "Formations et ressources permettant d'élaborer une stratégie sur la collecte de fonds", 'type' => 'scale'],
                    ['id' => 'fd_reunions_regulieres', 'text' => 'Réunions régulières des responsables Finances et Collecte de fonds pour évoquer les performances', 'type' => 'scale'],
                ],
            ],
        ],
    ],
    [
        'id' => 'lutte_corruption',
        'title' => 'Lutte contre la corruption',
        'subsections' => [
            [
                'id' => 'politiques_corruption',
                'title' => 'Politiques anti-corruption',
                'questions' => [
                    ['id' => 'lc_code_conduite', 'text' => 'Code de conduite définissant clairement la corruption, la fraude et les procédures de prévention', 'type' => 'scale'],
                    ['id' => 'lc_politiques_achat', 'text' => "Politiques et procédures d'achat visant à faciliter la gestion des risques de corruption", 'type' => 'scale'],
                    ['id' => 'lc_collaboration_rh', 'text' => "Collaboration entre l'équipe Finances et les ressources humaines pour garantir des clauses adaptées", 'type' => 'scale'],
                    ['id' => 'lc_seances_remise', 'text' => 'Séances de remise à niveau organisées de façon régulière, au moins tous les deux ans', 'type' => 'scale'],
                    ['id' => 'lc_journal_incidents', 'text' => 'Journal des dons et des incidents, y compris ceux évités de justesse', 'type' => 'scale'],
                    ['id' => 'lc_membre_ca_alerte', 'text' => "Membre du CA désigné·e comme personne à contacter en cas d'incident nécessitant de lancer une alerte", 'type' => 'scale'],
                ],
            ],
        ],
    ],
    [
        'id' => 'sante_organisationnelle',
        'title' => 'Normes de santé organisationnelle',
        'subsections' => [
            [
                'id' => 'employeur_progressiste',
                'title' => 'Employeur progressiste et respect des droits du travail',
                'questions' => [
                    ['id' => 'ep_recrutement_externe', 'text' => "Tout poste vacant fait l'objet d'une offre d'emploi publiée en dehors de l'organisation", 'type' => 'scale'],
                    ['id' => 'ep_non_discrimination', 'text' => 'Les politiques de recrutement excluent toute discrimination', 'type' => 'scale'],
                    ['id' => 'ep_descriptif_poste', 'text' => 'Les membres du personnel se voient remettre un descriptif de poste/mandat détaillé', 'type' => 'scale'],
                    ['id' => 'ep_contrat_valide', 'text' => 'Tous les membres du personnel disposent d\'un contrat de travail valide', 'type' => 'scale'],
                    ['id' => 'ep_politique_recrutement', 'text' => 'Vous avez une politique de recrutement détaillant les conditions et procédures', 'type' => 'scale'],
                    ['id' => 'ep_descriptif_benevoles', 'text' => 'Les bénévoles se voient remettre un descriptif de poste détaillé', 'type' => 'scale'],
                    ['id' => 'ep_salaire_minimum', 'text' => 'Tous les salaires sont supérieurs au salaire minimum et couvrent le minimum décent', 'type' => 'scale'],
                    ['id' => 'ep_equite_salariale', 'text' => "Les politiques relatives à la rémunération reposent sur le principe d'équité", 'type' => 'scale'],
                    ['id' => 'ep_non_discrimination_salaire', 'text' => "La rémunération est la même, indépendamment de l'origine ethnique, du genre, etc.", 'type' => 'scale'],
                    ['id' => 'ep_analyse_equite', 'text' => "L'équité salariale est effective et fait l'objet d'une analyse", 'type' => 'scale'],
                    ['id' => 'ep_ecart_remuneration', 'text' => "Les rapports sur les écarts de rémunération font état d'une différence n'excédant pas ± 5 %", 'type' => 'scale'],
                ],
            ],
            [
                'id' => 'egalite_diversite',
                'title' => 'Égalité, diversité et inclusion',
                'questions' => [
                    ['id' => 'edi_politiques_contraignantes', 'text' => "Des politiques internes contraignantes sont adoptées dans les domaines de l'égalité des genres", 'type' => 'scale'],
                    ['id' => 'edi_recrutement_antiracisme', 'text' => 'Les politiques de RH comportent des mesures de lutte contre le racisme et la discrimination', 'type' => 'scale'],
                    ['id' => 'edi_promotions_merite', 'text' => "Les promotions s'effectuent sur la foi du mérite et des résultats", 'type' => 'scale'],
                    ['id' => 'edi_gestion_performances', 'text' => 'La gestion des performances est réalisée de manière objective', 'type' => 'scale'],
                    ['id' => 'edi_conges_parentaux', 'text' => "Les congés maternité et parentaux font partie des droits des salarié·e·s", 'type' => 'scale'],
                    ['id' => 'edi_procedure_disciplinaire', 'text' => 'Une politique et une procédure disciplinaires sont élaborées pour traiter des cas de discrimination', 'type' => 'scale'],
                    ['id' => 'edi_procedure_griefs', 'text' => 'Une politique et une procédure anonyme de traitement des griefs', 'type' => 'scale'],
                    ['id' => 'edi_procedure_harcelement', 'text' => 'Une procédure visant à traiter les cas de harcèlement est en place', 'type' => 'scale'],
                    ['id' => 'edi_lancement_alerte', 'text' => "La procédure de lancement d'alerte est précisée dans les politiques de RH", 'type' => 'scale'],
                    ['id' => 'edi_formation_personnel', 'text' => "L'ensemble du personnel bénéficie d'une formation à toutes les procédures RH", 'type' => 'scale'],
                ],
            ],
        ],
    ],
    [
        'id' => 'risques_securite',
        'title' => 'Atténuation des risques, sécurité et protection',
        'subsections' => [
            [
                'id' => 'gestion_risques',
                'title' => 'Gestion des risques',
                'questions' => [
                    ['id' => 'gr_evaluation_reguliere', 'text' => "L'évaluation, la prévention et l'atténuation des principaux risques sont réalisées régulièrement", 'type' => 'scale'],
                    ['id' => 'gr_evaluation_trimestrielle', 'text' => 'Chaque trimestre, le directeur/la directrice transmet au CA une évaluation des risques émergents', 'type' => 'scale'],
                    ['id' => 'gr_communication_si', 'text' => 'Les organisations font part au SI des principaux risques auxquels elles sont exposées', 'type' => 'scale'],
                    ['id' => 'gr_procedure_reddition', 'text' => "Une procédure garantit la reddition de comptes et l'examen des incidents", 'type' => 'scale'],
                    ['id' => 'gr_plan_continuite', 'text' => 'Un Plan de continuité des activités est validé et revu au moins une fois par an', 'type' => 'scale'],
                ],
            ],
            [
                'id' => 'protection_donnees',
                'title' => 'Protection des données',
                'questions' => [
                    ['id' => 'pd_lignes_directrices', 'text' => "L'organisation dispose de lignes directrices conformes à la législation concernant la gestion des informations", 'type' => 'scale'],
                    ['id' => 'pd_achat_stockage', 'text' => 'Achat et stockage fiable des matériels et logiciels nécessaires à la sécurisation des données', 'type' => 'scale'],
                    ['id' => 'pd_calendriers_conservation', 'text' => 'Calendriers de conservation des données conformes à la législation et systèmes de stockage centralisés', 'type' => 'scale'],
                    ['id' => 'pd_formation_personnel', 'text' => 'Formation et initiation du personnel et des bénévoles aux lois relatives à la protection des données', 'type' => 'scale'],
                    ['id' => 'pd_archives_fiables', 'text' => "Archives ou méthodes d'archivage internes fiables", 'type' => 'scale'],
                    ['id' => 'pd_calendrier_conservation', 'text' => "Votre organisation dispose-t-elle d'un calendrier de conservation des informations ?", 'type' => 'scale'],
                    ['id' => 'pd_systemes_stockage', 'text' => 'Votre organisation dispose-t-elle de systèmes de stockage indépendants des espaces personnels ?', 'type' => 'scale'],
                ],
            ],
            [
                'id' => 'securite_personnel',
                'title' => 'Sécurité du personnel',
                'questions' => [
                    ['id' => 'sp_environnement_sur', 'text' => 'Le CA et la direction fournissent un environnement de travail sûr', 'type' => 'scale'],
                    ['id' => 'sp_politique_securite', 'text' => "Votre politique de sécurité couvre la sécurité de l'information", 'type' => 'scale'],
                    ['id' => 'sp_mesures_deplacements', 'text' => "Des mesures d'atténuation des risques en cas de déplacements potentiellement dangereux", 'type' => 'scale'],
                    ['id' => 'sp_assurance_appropriee', 'text' => "Les dispositions appropriées sont prises en matière d'assurance", 'type' => 'scale'],
                    ['id' => 'sp_politique_protection', 'text' => "Une politique de protection des personnes en contact avec l'organisation est en place", 'type' => 'scale'],
                ],
            ],
        ],
    ],
    [
        'id' => 'durabilite_environnementale',
        'title' => 'Durabilité environnementale',
        'subsections' => [
            [
                'id' => 'impact_environnemental',
                'title' => "Réduction de l'impact environnemental",
                'questions' => [
                    ['id' => 'de_programme_reduction', 'text' => "L'organisation a élaboré, mis en œuvre et suit un programme de réduction de l'impact environnemental", 'type' => 'scale'],
                    ['id' => 'de_neutralite_carbone', 'text' => "L'organisation respecte les exigences et les cibles de neutralité carbone", 'type' => 'scale'],
                    ['id' => 'de_politiques_gestion', 'text' => "Politiques de gestion et de réduction de la consommation d'énergie, des déplacements, des émissions", 'type' => 'scale'],
                    ['id' => 'de_cibles_scientifiques', 'text' => 'Adhésion aux cibles scientifiques de zéro carbone net/absolu', 'type' => 'scale'],
                    ['id' => 'de_adoption_objectifs', 'text' => 'Adoption et respect des objectifs et du langage des campagnes pour la justice climatique', 'type' => 'scale'],
                    ['id' => 'de_compte_rendu', 'text' => 'Compte rendu selon une procédure normalisée (Global Reporting Initiative)', 'type' => 'scale'],
                    ['id' => 'de_collecte_donnees', 'text' => 'Collecte de données sur les émissions de type 1, 2 et 3', 'type' => 'scale'],
                    ['id' => 'de_desinvestissement', 'text' => 'Le désinvestissement des fonds placés dans les énergies fossiles', 'type' => 'scale'],
                ],
            ],
        ],
    ],
];

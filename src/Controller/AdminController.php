<?php

namespace App\Controller;

use App\Entity\Set;
use App\Repository\CatalogSetRepository;
use App\Repository\SetRepository;
use App\Service\CatalogSync;
use App\Service\LegoCatalog;
use App\Service\SetNumber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Espace propriétaire. Aucune information de réservation n'est jamais affichée ici.
 */
#[Route('/admin')]
class AdminController extends AbstractController
{
    private const IMPORT_MAX_LINES = 300;

    public function __construct(
        private readonly SetRepository $setRepository,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly LegoCatalog $catalog,
    ) {
    }

    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(CatalogSetRepository $catalogRepository): Response
    {
        $mine = array_map(fn (Set $s) => $s->getNumeroSet(), $this->setRepository->search('all', forOwner: true, showReservation: false));

        return $this->render('admin/dashboard.html.twig', [
            // Derniers véhicules sortis que tu n'as pas encore
            'news' => array_slice(iterator_to_array($catalogRepository->browse(excludeNumeros: $mine, minParts: 150)), 0, 6),
            'catalogEmpty' => $catalogRepository->isEmpty(),
            'stats' => $this->setRepository->getOwnerStats(),
            'recent' => array_slice($this->setRepository->search('all', sort: 'recent', forOwner: true, showReservation: false), 0, 8),
            'topWanted' => array_slice($this->setRepository->search('wanted', sort: 'priorite', forOwner: true, showReservation: false), 0, 5),
        ]);
    }

    #[Route('/sets', name: 'admin_sets', methods: ['GET'])]
    public function sets(Request $request): Response
    {
        $filter = $request->query->getString('filter', 'all');
        if (!in_array($filter, ['all', 'owned', 'wanted'], true)) {
            $filter = 'all';
        }
        $sort = $request->query->getString('sort', 'numero');
        if (!in_array($sort, SetRepository::SORTS, true)) {
            $sort = 'numero';
        }
        $q = $request->query->getString('q');
        $theme = $request->query->getString('theme') ?: null;

        return $this->render('admin/sets.html.twig', [
            'sets' => $this->setRepository->search($filter, $q, $theme, $sort, forOwner: true, showReservation: false),
            'filter' => $filter,
            'sort' => $sort,
            'q' => $q,
            'theme' => $theme,
            'themes' => $this->setRepository->findThemes(true),
        ]);
    }

    #[Route('/sets/new', name: 'admin_sets_new', methods: ['GET', 'POST'])]
    public function newSet(Request $request): Response
    {
        $set = new Set();

        if ($request->isMethod('GET')) {
            // Pré-remplissage depuis le tableau de bord (« Ajout rapide »)
            $set->setNumeroSet($request->query->getString('numero') ?: null);
            $set->setOwned($request->query->getBoolean('owned', true));
            if ($set->getNumeroSet() && ($info = $this->catalog->find($set->getNumeroSet()))) {
                $this->applyCatalogInfo($set, $info);
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('set_form', $request->request->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré, réessaie.');

                return $this->redirectToRoute('admin_sets_new');
            }

            $this->fillFromRequest($set, $request);
            $existing = $this->setRepository->findOneByNumero($set->getNumeroSet());

            if ($existing && !$this->isGiverSecret($existing)) {
                $this->addFlash('error', sprintf('Le set %s est déjà dans ta collection.', $existing->getNumeroSet()));

                return $this->redirectToRoute('admin_sets_edit', ['id' => $existing->getId()]);
            }

            if ($existing) {
                // Un proche l'a ajouté en secret : on reprend la même ligne sans rien révéler
                $this->fillFromRequest($existing, $request);
                $existing->setAddedByGiver(false);
                $set = $existing;
            }

            if ($this->validateSet($set)) {
                $this->em->persist($set);
                $this->em->flush();
                $this->addFlash('success', sprintf('« %s » ajouté !', $set->getNom()));

                return $request->request->getBoolean('add_another')
                    ? $this->redirectToRoute('admin_sets_new', ['owned' => (int) $set->isOwned()])
                    : $this->redirectToRoute('admin_sets');
            }
        }

        return $this->render('admin/set_form.html.twig', [
            'set' => $set,
            'title' => 'Ajouter un set',
            'catalogEnabled' => $this->catalog->isEnabled(),
        ]);
    }

    #[Route('/sets/{id}/edit', name: 'admin_sets_edit', methods: ['GET', 'POST'])]
    public function editSet(Set $set, Request $request): Response
    {
        if ($this->isGiverSecret($set)) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('set_form', $request->request->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré, réessaie.');

                return $this->redirectToRoute('admin_sets_edit', ['id' => $set->getId()]);
            }

            $previousNumero = $set->getNumeroSet();
            $this->fillFromRequest($set, $request);
            $duplicate = $set->getNumeroSet() !== $previousNumero ? $this->setRepository->findOneByNumero($set->getNumeroSet()) : null;

            if ($duplicate) {
                $this->em->refresh($set);
                $this->addFlash('error', sprintf('Le numéro %s est déjà utilisé par un autre set.', $duplicate->getNumeroSet()));
            } elseif ($this->validateSet($set)) {
                $this->em->flush();
                $this->addFlash('success', sprintf('« %s » modifié.', $set->getNom()));

                return $this->redirectToRoute('admin_sets');
            }
        }

        return $this->render('admin/set_form.html.twig', [
            'set' => $set,
            'title' => 'Modifier le set',
            'catalogEnabled' => $this->catalog->isEnabled(),
        ]);
    }

    /**
     * « Je l'ai ! » / « Je ne l'ai plus » en un clic depuis la liste.
     */
    #[Route('/sets/{id}/toggle-owned', name: 'admin_sets_toggle_owned', methods: ['POST'])]
    public function toggleOwned(Set $set, Request $request): Response
    {
        if (!$this->isGiverSecret($set) && $this->isCsrfTokenValid('toggle'.$set->getId(), $request->request->getString('_token'))) {
            $set->setOwned(!$set->isOwned());
            $this->em->flush();
            $this->addFlash('success', $set->isOwned()
                ? sprintf('🎉 « %s » rejoint ta collection !', $set->getNom())
                : sprintf('« %s » repasse dans tes souhaits.', $set->getNom()));
        }

        return $this->redirect($this->safeReferer($request));
    }

    #[Route('/sets/{id}/delete', name: 'admin_sets_delete', methods: ['POST'])]
    public function deleteSet(Set $set, Request $request): Response
    {
        if (!$this->isGiverSecret($set) && $this->isCsrfTokenValid('delete'.$set->getId(), $request->request->getString('_token'))) {
            $this->em->remove($set);
            $this->em->flush();
            $this->addFlash('success', sprintf('« %s » supprimé.', $set->getNom()));
        }

        return $this->redirectToRoute('admin_sets');
    }

    /**
     * Remplissage automatique du formulaire (appelé en JS).
     */
    #[Route('/lookup/{numero}', name: 'admin_lookup', methods: ['GET'])]
    public function lookup(string $numero): JsonResponse
    {
        $existing = $this->setRepository->findOneByNumero($numero);
        if ($existing && $this->isGiverSecret($existing)) {
            $existing = null;
        }

        return $this->json([
            'found' => ($info = $this->catalog->find($numero)) !== null,
            'set' => $info,
            'existing' => $existing ? [
                'id' => $existing->getId(),
                'nom' => $existing->getNom(),
                'editUrl' => $this->generateUrl('admin_sets_edit', ['id' => $existing->getId()]),
            ] : null,
        ]);
    }

    /**
     * Parcourir tous les sets véhicules existants et les ajouter en un clic.
     */
    #[Route('/catalogue', name: 'admin_catalog', methods: ['GET'])]
    public function catalog(Request $request, CatalogSetRepository $catalogRepository): Response
    {
        $vehiclesOnly = $request->query->getString('tous') !== '1';
        $hideMine = $request->query->getBoolean('masquer');
        $page = max(1, $this->queryInt($request, 'page') ?? 1);
        $sort = $request->query->getString('sort', 'recent');
        $fromYear = $this->queryInt($request, 'de');
        $toYear = $this->queryInt($request, 'a');
        $q = $request->query->getString('q');
        $theme = $request->query->getString('theme') ?: null;

        // Statut de chaque numéro dans la collection : numero => 'owned' | 'wanted'
        $mine = [];
        foreach ($this->setRepository->search('all', forOwner: true, showReservation: false) as $set) {
            $mine[$set->getNumeroSet()] = $set->isOwned() ? 'owned' : 'wanted';
        }

        $results = $catalogRepository->browse($vehiclesOnly, $q, $theme, $fromYear, $toYear, $hideMine ? array_keys($mine) : [], $sort, $page);
        $total = count($results);

        return $this->render('admin/catalog.html.twig', [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / CatalogSetRepository::PAGE_SIZE),
            'mine' => $mine,
            'themes' => $catalogRepository->rootThemes($vehiclesOnly),
            'summary' => $catalogRepository->summary(),
            'filters' => [
                'q' => $q, 'theme' => $theme, 'de' => $fromYear, 'a' => $toYear, 'sort' => $sort,
                'tous' => $vehiclesOnly ? null : '1', 'masquer' => $hideMine ? '1' : null,
            ],
        ]);
    }

    #[Route('/catalogue/set/{numero}', name: 'admin_catalog_show', methods: ['GET'])]
    public function catalogShow(string $numero, CatalogSetRepository $catalogRepository): Response
    {
        $item = $catalogRepository->findOneByNumero($numero) ?? throw $this->createNotFoundException('Set introuvable dans le catalogue.');
        $mine = $this->setRepository->findOneByNumero($item->getNumero());
        if ($mine && $this->isGiverSecret($mine)) {
            $mine = null;
        }

        return $this->render('admin/catalog_show.html.twig', ['item' => $item, 'mine' => $mine]);
    }

    #[Route('/catalogue/ajouter', name: 'admin_catalog_add', methods: ['POST'])]
    public function catalogAdd(Request $request): Response
    {
        $back = $this->safeReferer($request, 'admin_catalog');
        if (!$this->isCsrfTokenValid('catalog_add', $request->request->getString('_token'))) {
            $this->addFlash('error', 'La page a expiré, réessaie.');

            return $this->redirect($back);
        }

        $numero = SetNumber::normalize($request->request->getString('numero'));
        $owned = $request->request->getBoolean('owned');
        $info = $numero ? $this->catalog->find($numero) : null;
        if (!$info) {
            $this->addFlash('error', 'Set introuvable dans le catalogue.');

            return $this->redirect($back);
        }

        $set = $this->setRepository->findOneByNumero($numero);
        if ($set && !$this->isGiverSecret($set)) {
            // Déjà là : on met juste à jour le statut demandé
            $set->setOwned($owned);
        } else {
            $set ??= new Set();
            $set->setNumeroSet($numero)->setAddedByGiver(false)->setOwned($owned);
            $this->applyCatalogInfo($set, $info);
            $this->em->persist($set);
        }
        $this->em->flush();

        $this->addFlash('success', sprintf($owned ? '🏠 « %s » ajouté à ta collection.' : '⭐ « %s » ajouté à tes souhaits.', $set->getNom()));

        return $this->redirect($back.'#set-'.rawurlencode($numero));
    }

    #[Route('/catalogue/synchroniser', name: 'admin_catalog_sync', methods: ['POST'])]
    public function catalogSync(Request $request, CatalogSync $sync): Response
    {
        if ($this->isCsrfTokenValid('catalog_sync', $request->request->getString('_token'))) {
            try {
                $result = $sync->sync();
                $this->addFlash('success', sprintf('Catalogue à jour : %s sets, dont %s véhicules.', number_format($result['total'], 0, ',', ' '), number_format($result['vehicles'], 0, ',', ' ')));
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Mise à jour impossible : '.$e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_catalog');
    }

    #[Route('/import', name: 'admin_import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        $report = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('import', $request->request->getString('_token'))) {
                $this->addFlash('error', 'La page a expiré, réessaie.');

                return $this->redirectToRoute('admin_import');
            }

            $report = $this->importLines(
                $this->readImportSource($request),
                $request->request->getString('default_status', 'owned') === 'owned',
            );
        }

        return $this->render('admin/import.html.twig', [
            'report' => $report,
            'catalogEnabled' => $this->catalog->isEnabled(),
        ]);
    }

    #[Route('/export.csv', name: 'admin_export', methods: ['GET'])]
    public function export(): StreamedResponse
    {
        $sets = $this->setRepository->search('all', sort: 'numero', forOwner: true, showReservation: false);

        $response = new StreamedResponse(function () use ($sets) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM pour qu'Excel lise les accents
            fputcsv($out, ['numero', 'nom', 'theme', 'annee', 'possede', 'pieces', 'prix', 'priorite', 'notes'], ';', '"', '');
            foreach ($sets as $set) {
                fputcsv($out, [
                    $set->getNumeroSet(), $set->getNom(), $set->getTheme(), $set->getAnnee(),
                    $set->isOwned() ? 'oui' : 'non', $set->getPieces(), $set->getPrix(), $set->getPriorite(), $set->getNotes(),
                ], ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="collection-lego-'.date('Y-m-d').'.csv"');

        return $response;
    }

    /**
     * @return string[]
     */
    private function readImportSource(Request $request): array
    {
        $text = $request->request->getString('lines');
        $file = $request->files->get('file');
        if ($file && $file->isValid() && $file->getSize() < 1_000_000) {
            $text .= "\n".file_get_contents($file->getPathname());
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn ($l) => $l !== ''));
    }

    /**
     * Format par ligne : numero[;nom[;theme[;annee[;possédé oui/non]]]] — le reste est ignoré.
     * Compatible avec l'export CSV.
     *
     * @param string[] $lines
     *
     * @return array{added: string[], skipped: string[], errors: string[], truncated: bool}
     */
    private function importLines(array $lines, bool $defaultOwned): array
    {
        $report = ['added' => [], 'skipped' => [], 'errors' => [], 'truncated' => count($lines) > self::IMPORT_MAX_LINES];
        $seen = [];

        foreach (array_slice($lines, 0, self::IMPORT_MAX_LINES) as $i => $line) {
            $cols = array_map('trim', str_getcsv($line, str_contains($line, ';') ? ';' : "\t", '"', ''));
            $numero = SetNumber::normalize($cols[0] ?? null);

            if (!$numero || $numero === 'numero') {
                continue; // ligne d'en-tête ou vide
            }
            if (isset($seen[$numero])) {
                continue;
            }
            $seen[$numero] = true;

            $existing = $this->setRepository->findOneByNumero($numero);
            if ($existing && !$this->isGiverSecret($existing)) {
                $report['skipped'][] = $numero.' (déjà présent)';
                continue;
            }

            $set = $existing ?? new Set();
            $set->setNumeroSet($numero)->setAddedByGiver(false);

            $info = empty($cols[1]) ? $this->catalog->find($numero) : null;
            if ($info) {
                $this->applyCatalogInfo($set, $info);
            } else {
                $set->setNom(($cols[1] ?? null) ?: 'Set '.$numero)
                    ->setTheme($cols[2] ?? null)
                    ->setAnnee(isset($cols[3]) && ctype_digit($cols[3]) ? (int) $cols[3] : null);
            }

            $ownedCol = mb_strtolower($cols[4] ?? '');
            $set->setOwned($ownedCol === '' ? $defaultOwned : in_array($ownedCol, ['oui', 'o', 'yes', 'y', '1', 'x', 'true'], true));

            $errors = $this->validator->validate($set);
            if (count($errors) > 0) {
                $report['errors'][] = sprintf('Ligne %d (%s) : %s', $i + 1, $numero, $errors[0]->getMessage());
                if ($existing) {
                    $this->em->refresh($existing);
                }
                continue;
            }

            $this->em->persist($set);
            $report['added'][] = $numero.' – '.$set->getNom();
        }

        $this->em->flush();

        return $report;
    }

    private function fillFromRequest(Set $set, Request $request): void
    {
        $r = $request->request;
        $set->setNumeroSet($r->getString('numero_set'))
            ->setNom($r->getString('nom'))
            ->setTheme($r->getString('theme'))
            ->setAnnee(ctype_digit($r->getString('annee')) ? (int) $r->getString('annee') : null)
            ->setPieces(ctype_digit($r->getString('pieces')) ? (int) $r->getString('pieces') : null)
            ->setPrix($r->getString('prix'))
            ->setPriorite($r->getInt('priorite', Set::PRIORITY_NORMAL))
            ->setImageUrl($r->getString('image_url'))
            ->setNotes($r->getString('notes'))
            ->setOwned($r->getBoolean('owned'));
    }

    private function applyCatalogInfo(Set $set, array $info): void
    {
        $set->setNom($info['nom'])
            ->setTheme($info['theme'])
            ->setAnnee($info['annee'])
            ->setPieces($info['pieces'])
            ->setImageUrl($info['imageUrl']);
    }

    private function validateSet(Set $set): bool
    {
        $errors = $this->validator->validate($set);
        foreach ($errors as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return count($errors) === 0;
    }

    private function isGiverSecret(Set $set): bool
    {
        return $set->isAddedByGiver() && !$set->isOwned();
    }

    /**
     * Entier facultatif de l'URL. Un champ de formulaire vide (« de= ») donne null,
     * là où getInt() lèverait une erreur 400.
     */
    private function queryInt(Request $request, string $key): ?int
    {
        $value = trim($request->query->getString($key));

        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function safeReferer(Request $request, string $fallbackRoute = 'admin_sets'): string
    {
        $referer = strtok((string) $request->headers->get('referer'), '#');

        return $referer && str_starts_with($referer, $request->getSchemeAndHttpHost().'/admin')
            ? $referer
            : $this->generateUrl($fallbackRoute);
    }
}

<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Set;
use App\Repository\ReservationRepository;
use App\Repository\SetRepository;
use App\Service\LegoCatalog;
use App\Service\ReservationHashService;
use App\Service\SetNumber;
use App\Service\Viewer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/gift')]
class GiftController extends AbstractController
{
    public function __construct(
        private readonly Viewer $viewer,
        private readonly SetRepository $setRepository,
    ) {
    }

    #[Route('/', name: 'gift_home', methods: ['GET'])]
    public function home(): Response
    {
        if (!$this->viewer->hasChosen()) {
            return $this->render('gift/welcome.html.twig');
        }

        $sets = $this->setRepository->search('available', sort: 'priorite', forOwner: $this->viewer->isOwner(), showReservation: $this->viewer->canSeeReservations());

        return $this->render('gift/home.html.twig', [
            'highlights' => array_slice($sets, 0, 6),
            'availableCount' => count($sets),
        ]);
    }

    #[Route('/je-suis/{role}', name: 'gift_role', requirements: ['role' => 'proche|proprietaire|personne'], methods: ['GET'])]
    public function role(string $role): Response
    {
        if ($role === 'proprietaire') {
            // Le cookie « propriétaire » est posé à la connexion admin (OwnerCookieSubscriber)
            return $this->redirectToRoute('admin_dashboard');
        }

        $response = $this->redirectToRoute('gift_home');
        $response->headers->setCookie(Viewer::roleCookie(Viewer::GIVER_COOKIE, $role === 'proche'));
        if ($role === 'personne') {
            $response->headers->setCookie(Viewer::roleCookie(Viewer::OWNER_COOKIE, false));
        }

        return $response;
    }

    #[Route('/list', name: 'gift_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $filter = $request->query->getString('filter', $this->viewer->canSeeReservations() ? 'available' : 'wanted');
        if (!in_array($filter, SetRepository::FILTERS, true)) {
            $filter = 'all';
        }
        $sort = $request->query->getString('sort', 'priorite');
        if (!in_array($sort, SetRepository::SORTS, true)) {
            $sort = 'priorite';
        }
        $q = $request->query->getString('q');
        $theme = $request->query->getString('theme') ?: null;

        return $this->render('gift/list.html.twig', [
            'sets' => $this->setRepository->search($filter, $q, $theme, $sort, forOwner: $this->viewer->isOwner(), showReservation: $this->viewer->canSeeReservations()),
            'filter' => $filter,
            'sort' => $sort,
            'q' => $q,
            'theme' => $theme,
            'themes' => $this->setRepository->findThemes($this->viewer->isOwner()),
        ]);
    }

    #[Route('/search', name: 'gift_search', methods: ['GET'])]
    public function search(Request $request, LegoCatalog $catalog): Response
    {
        $q = trim($request->query->getString('q'));
        if ($q === '') {
            $this->addFlash('error', 'Tape un numéro ou un nom de set.');

            return $this->redirectToRoute('gift_home');
        }

        $set = $this->setRepository->findOneByNumero($q);
        if ($set && !$this->isHiddenFromOwner($set)) {
            return $this->redirectToSet($set);
        }

        $results = $this->setRepository->search('all', $q, forOwner: $this->viewer->isOwner(), showReservation: $this->viewer->canSeeReservations());
        if (count($results) === 1) {
            return $this->redirectToSet($results[0]);
        }
        if (count($results) > 1) {
            return $this->redirectToRoute('gift_list', ['filter' => 'all', 'q' => $q]);
        }

        if (SetNumber::looksLikeNumber($q)) {
            $numero = SetNumber::normalize($q);

            return $this->render('gift/not_listed.html.twig', [
                'numero' => $numero,
                'info' => $catalog->find($numero),
                'imageUrl' => SetNumber::rebrickableImage($numero),
            ]);
        }

        return $this->render('gift/no_result.html.twig', ['q' => $q]);
    }

    #[Route('/set/{numero}', name: 'gift_detail', methods: ['GET'])]
    public function detail(#[MapEntity(mapping: ['numero' => 'numeroSet'])] Set $set): Response
    {
        if ($this->isHiddenFromOwner($set)) {
            return $this->redirectToRoute('gift_search', ['q' => $set->getNumeroSet()]);
        }

        return $this->render('gift/detail.html.twig', ['set' => $set]);
    }

    #[Route('/reserve/{id}', name: 'gift_reserve', methods: ['POST'])]
    public function reserve(Set $set, Request $request, EntityManagerInterface $em, ReservationHashService $hashService): Response
    {
        if (!$this->isCsrfTokenValid('reserve'.$set->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'La page a expiré, réessaie.');

            return $this->redirectToSet($set);
        }
        if (!$this->viewer->isGiver()) {
            return $this->redirectToRoute('gift_home');
        }
        if ($set->isOwned()) {
            $this->addFlash('error', sprintf('%s possède déjà ce set.', $this->getParameter('app.owner_name')));

            return $this->redirectToSet($set);
        }
        if ($set->isReserved()) {
            $this->addFlash('error', 'Ce set vient d\'être réservé par quelqu\'un d\'autre.');

            return $this->redirectToSet($set);
        }

        return $this->createReservation($set, $request, $em, $hashService);
    }

    /**
     * Un proche veut offrir un set qui n'est pas dans la liste : on le crée en secret et on le réserve,
     * pour que les autres proches ne l'achètent pas aussi.
     */
    #[Route('/offrir-hors-liste', name: 'gift_offer_unlisted', methods: ['POST'])]
    public function offerUnlisted(Request $request, EntityManagerInterface $em, ReservationHashService $hashService, LegoCatalog $catalog, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('offer_unlisted', $request->request->getString('_token')) || !$this->viewer->isGiver()) {
            return $this->redirectToRoute('gift_home');
        }

        $numero = SetNumber::normalize($request->request->getString('numero'));
        if ($existing = $this->setRepository->findOneByNumero($numero)) {
            return $this->redirectToSet($existing);
        }

        $info = $numero ? $catalog->find($numero) : null;
        $set = (new Set())
            ->setNumeroSet($numero)
            ->setNom($request->request->getString('nom') ?: ($info['nom'] ?? null))
            ->setTheme($info['theme'] ?? null)
            ->setAnnee($info['annee'] ?? null)
            ->setPieces($info['pieces'] ?? null)
            ->setImageUrl($info['imageUrl'] ?? null)
            ->setAddedByGiver(true);

        $errors = $validator->validate($set);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('gift_search', ['q' => $numero]);
        }

        $em->persist($set);

        return $this->createReservation($set, $request, $em, $hashService);
    }

    #[Route('/mes-reservations', name: 'gift_my_reservations', methods: ['GET'])]
    public function myReservations(Request $request, ReservationRepository $reservationRepository): Response
    {
        $mine = $this->viewer->getMyReservations();
        $reservations = $mine ? $reservationRepository->findBy(['anonymousId' => array_keys($mine)]) : [];

        $items = [];
        foreach ($reservations as $reservation) {
            $items[] = ['reservation' => $reservation, 'code' => $mine[$reservation->getAnonymousId()]];
        }
        usort($items, fn ($a, $b) => $b['reservation']->getReservedAt() <=> $a['reservation']->getReservedAt());

        // Nettoie le cookie des réservations qui n'existent plus (set supprimé par l'admin)
        $found = array_map(fn (Reservation $r) => $r->getAnonymousId(), $reservations);
        $lost = count($mine) - count($found);

        $response = $this->render('gift/my_reservations.html.twig', [
            'items' => $items,
            'lost' => $lost,
            'new' => $request->query->getString('new'),
        ]);
        if ($lost > 0) {
            $response->headers->setCookie(Viewer::myReservationsCookie(array_intersect_key($mine, array_flip($found))));
        }

        return $response;
    }

    #[Route('/annuler', name: 'gift_cancel', methods: ['POST'])]
    public function cancel(Request $request, EntityManagerInterface $em, ReservationRepository $reservationRepository, ReservationHashService $hashService): Response
    {
        if (!$this->isCsrfTokenValid('cancel', $request->request->getString('_token'))) {
            $this->addFlash('error', 'La page a expiré, réessaie.');

            return $this->redirectToRoute('gift_my_reservations');
        }

        $mine = $this->viewer->getMyReservations();
        $anonymousId = $request->request->getString('reservation');
        $code = $anonymousId ? ($mine[$anonymousId] ?? '') : $request->request->getString('code');

        $reservation = $anonymousId
            ? $reservationRepository->findOneBy(['anonymousId' => $anonymousId])
            : $this->setRepository->findOneByNumero($request->request->getString('numero'))?->getReservation();

        if (!$reservation || !$hashService->isCancelCodeValid($code, $reservation->getCancelCodeHash())) {
            $this->addFlash('error', 'Numéro de set ou code d\'annulation incorrect.');

            return $this->redirectToRoute('gift_my_reservations');
        }

        $set = $reservation->getSet();
        $set->removeReservation($reservation);
        $em->remove($reservation);
        // Le set n'avait été créé que pour cette réservation : il disparaît avec elle
        if ($set->isAddedByGiver() && !$set->isOwned()) {
            $em->remove($set);
        }
        $em->flush();

        unset($mine[$reservation->getAnonymousId()]);
        $this->addFlash('success', sprintf('Réservation du set %s annulée. Il est de nouveau disponible pour les autres.', $set->getNumeroSet()));

        $response = $this->redirectToRoute('gift_my_reservations');
        $response->headers->setCookie(Viewer::myReservationsCookie($mine));

        return $response;
    }

    private function createReservation(Set $set, Request $request, EntityManagerInterface $em, ReservationHashService $hashService): Response
    {
        $code = $hashService->generateCancelCode();
        $reservation = (new Reservation())
            ->setAnonymousId($hashService->generateAnonymousId())
            ->setReservedByHash($hashService->hashReservationData($request->request->getString('reserved_by')))
            ->setCancelCodeHash($hashService->hashCancelCode($code));
        $set->addReservation($reservation);
        $em->persist($reservation);

        try {
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('error', 'Ce set vient d\'être réservé par quelqu\'un d\'autre.');

            return $this->redirectToRoute('gift_home');
        }

        $mine = $this->viewer->getMyReservations();
        $mine[$reservation->getAnonymousId()] = $code;

        $response = $this->redirectToRoute('gift_my_reservations', ['new' => $reservation->getAnonymousId()]);
        $response->headers->setCookie(Viewer::myReservationsCookie($mine));

        return $response;
    }

    /**
     * Un set ajouté en secret par un proche n'existe pas pour le propriétaire.
     */
    private function isHiddenFromOwner(Set $set): bool
    {
        return $set->isAddedByGiver() && !$set->isOwned() && $this->viewer->isOwner();
    }

    private function redirectToSet(Set $set): RedirectResponse
    {
        return $this->redirectToRoute('gift_detail', ['numero' => $set->getNumeroSet()]);
    }
}

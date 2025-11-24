<?php

namespace App\Controller;

use App\Entity\Set;
use App\Entity\Reservation;
use App\Repository\SetRepository;
use App\Service\ReservationHashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gift')]
class GiftController extends AbstractController
{
    #[Route('/', name: 'gift_home')]
    public function home(): Response
    {
        return $this->render('gift/home.html.twig');
    }

    #[Route('/list', name: 'gift_list')]
    public function list(SetRepository $setRepository, Request $request): Response
    {
        $filter = $request->query->get('filter', 'all'); // all, wanted, available
        
        switch ($filter) {
            case 'available':
                $sets = $setRepository->findAvailableSets();
                break;
            case 'wanted':
                $sets = $setRepository->findWantedSets();
                break;
            default:
                $sets = $setRepository->findAll();
        }

        return $this->render('gift/list.html.twig', [
            'sets' => $sets,
            'filter' => $filter,
        ]);
    }

    #[Route('/search', name: 'gift_search', methods: ['POST'])]
    public function search(Request $request, SetRepository $setRepository): Response
    {
        $numeroSet = $request->request->get('numero_set');
        
        if (!$numeroSet) {
            $this->addFlash('error', 'Veuillez saisir un numéro de set.');
            return $this->redirectToRoute('gift_home');
        }

        $set = $setRepository->findOneBy(['numeroSet' => $numeroSet]);
        
        if (!$set) {
            $this->addFlash('error', "Le set n°{$numeroSet} n'est pas dans la liste.");
            return $this->redirectToRoute('gift_home');
        }

        return $this->render('gift/detail.html.twig', [
            'set' => $set,
        ]);
    }

    #[Route('/reserve/{id}', name: 'gift_reserve', methods: ['POST'])]
    public function reserve(Set $set, Request $request, EntityManagerInterface $em, ReservationHashService $hashService): Response
    {
        if ($set->isOwned()) {
            $this->addFlash('error', 'Ce set est déjà possédé par cette personne.');
            return $this->redirectToRoute('gift_home');
        }
        
        if ($set->isReserved()) {
            $this->addFlash('error', 'Ce set est déjà réservé.');
            return $this->redirectToRoute('gift_home');
        }

        $reservedBy = $request->request->get('reserved_by', '');
        
        $reservation = new Reservation();
        $reservation->setSet($set);
        $reservation->setAnonymousId($hashService->generateAnonymousId());
        
        // Hash le prénom si fourni, sinon null
        if ($reservedBy) {
            $reservation->setReservedByHash($hashService->hashReservationData($reservedBy));
        }

        try {
            $em->persist($reservation);
            $em->flush();
            
            $this->addFlash('success', "Le set {$set->getNom()} a été réservé avec succès !");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Ce set est déjà réservé par quelqu\'un d\'autre.');
        }

        return $this->redirectToRoute('gift_home');
    }
}

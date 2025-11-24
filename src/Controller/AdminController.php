<?php

namespace App\Controller;

use App\Entity\Set;
use App\Repository\SetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        return $this->redirectToRoute('admin_sets');
    }

    #[Route('/sets', name: 'admin_sets')]
    public function sets(SetRepository $setRepository): Response
    {
        $sets = $setRepository->findAll();

        return $this->render('admin/sets.html.twig', [
            'sets' => $sets,
        ]);
    }

    #[Route('/sets/new', name: 'admin_sets_new', methods: ['GET', 'POST'])]
    public function newSet(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $set = new Set();

        if ($request->isMethod('POST')) {
            $set->setNumeroSet($request->request->get('numero_set'));
            $set->setNom($request->request->get('nom'));
            $set->setTheme($request->request->get('theme'));
            $set->setAnnee($request->request->get('annee') ? (int) $request->request->get('annee') : null);
            $set->setImageUrl($request->request->get('image_url'));
            $set->setOwned($request->request->getBoolean('owned'));

            $errors = $validator->validate($set);
            
            if (count($errors) === 0) {
                try {
                    $em->persist($set);
                    $em->flush();
                    
                    $this->addFlash('success', 'Set ajouté avec succès !');
                    return $this->redirectToRoute('admin_sets');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur : ce numéro de set existe déjà.');
                }
            } else {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('admin/set_form.html.twig', [
            'set' => $set,
            'title' => 'Ajouter un set',
        ]);
    }

    #[Route('/sets/{id}/edit', name: 'admin_sets_edit', methods: ['GET', 'POST'])]
    public function editSet(Set $set, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        if ($request->isMethod('POST')) {
            $set->setNumeroSet($request->request->get('numero_set'));
            $set->setNom($request->request->get('nom'));
            $set->setTheme($request->request->get('theme'));
            $set->setAnnee($request->request->get('annee') ? (int) $request->request->get('annee') : null);
            $set->setImageUrl($request->request->get('image_url'));
            $set->setOwned($request->request->getBoolean('owned'));

            $errors = $validator->validate($set);
            
            if (count($errors) === 0) {
                try {
                    $em->flush();
                    
                    $this->addFlash('success', 'Set modifié avec succès !');
                    return $this->redirectToRoute('admin_sets');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur : ce numéro de set existe déjà.');
                }
            } else {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('admin/set_form.html.twig', [
            'set' => $set,
            'title' => 'Modifier le set',
        ]);
    }

    #[Route('/sets/{id}/delete', name: 'admin_sets_delete', methods: ['POST'])]
    public function deleteSet(Set $set, EntityManagerInterface $em): Response
    {
        try {
            $em->remove($set);
            $em->flush();
            
            $this->addFlash('success', 'Set supprimé avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression.');
        }

        return $this->redirectToRoute('admin_sets');
    }
}

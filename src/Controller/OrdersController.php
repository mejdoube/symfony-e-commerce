<?php

namespace App\Controller;

use App\Entity\Orders;
use App\Entity\OrdersDetails;
use App\Repository\OrdersDetailsRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;  // Correct import

#[Route('/commandes', name: 'app_orders_')]
class OrdersController extends AbstractController
{ private EntityManagerInterface $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    #[Route('/ajout', name: 'add')]
    public function add(SessionInterface $session, ProductsRepository $productsRepository, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $panier = $session->get('panier', []);

        if($panier === []){
            $this->addFlash('message', 'Votre panier est vide');
            return $this->redirectToRoute('main');
        }

        //Le panier n'est pas vide, on crée la commande
        $order = new Orders();

        // On remplit la commande
        $order->setUsers($this->getUser());
        $order->setReference(uniqid());

        // On parcourt le panier pour créer les détails de commande
        foreach($panier as $item => $quantity){
            $orderDetails = new OrdersDetails();

            // On va chercher le produit
            $product = $productsRepository->find($item);
            
            $price = $product->getPrice();

            // On crée le détail de commande
            $orderDetails->setProducts($product);
            $orderDetails->setPrice($price);
            $orderDetails->setQuantity($quantity);

            $order->addOrdersDetail($orderDetails);
        }

        // On persiste et on flush
        $em->persist($order);
        $em->flush();

        $session->remove('panier');

        $this->addFlash('message', 'Commande créée avec succès');
        return $this->redirectToRoute('main');
    }

    #[Route('/toutes', name: 'all')]
    public function getAllOrders(OrdersRepository $orders): Response
    {

        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        // Fetch all orders from the repository
        $data = $orders->findAll();

        // Return a response (You can render a template or return a JSON response, as needed)
        return $this->render('admin/orders/orders.html.twig', [
            'orders' => $data,
        ]);
    }

    #[Route('/', name: 'my_orders')]
    public function getMyOrders(OrdersRepository $ordersRepository, UserInterface $user): Response
    {
        // Fetch only the orders for the currently authenticated user
        $myOrders = $ordersRepository->findBy(['users' => $user]);

        // Return the response and pass the filtered orders to the template
        return $this->render('orders/index.html.twig', [
            'orders' => $myOrders,
        ]);
    }
    #[Route('/order/{id}/accept', name: 'order_accept')]
    public function acceptOrder(Orders $order): Response
    {
        // Ensure the order exists and set its status to accepted
        $order->setStatus('1');  // Livré accepté

        // Persist the changes using the injected EntityManager
        $this->entityManager->flush();

        // Redirect to the order list or order details page
        return $this->redirectToRoute('app_orders_all');
    }

    #[Route('/order/refuse/{id}', name: 'order_refuse')]
    public function refuseOrder(int $id, OrdersRepository $ordersRepository): Response
    {
        // Fetch the order using the provided ID
        $order = $ordersRepository->find($id);

        // If the order exists, update its status to -1 (Refusé)
        if ($order) {
            $order->setStatus('-1');  // Set status to "Refusé"

            // Persist the changes
            $this->entityManager->flush();
        }

        // Redirect to the orders list or another appropriate page
        return $this->redirectToRoute('app_orders_all');
    }

    #[Route('/order/undo/{id}', name: 'order_undo')]
    public function undoStatus(int $id, OrdersRepository $ordersRepository): Response
    {
        // Fetch the order from the repository using the provided ID
        $order = $ordersRepository->find($id);

        // If the order exists, process the status change
        if ($order) {
            // If the order was accepted (status 1) or refused (status -1),
            // set it back to '0' (En cours)
            if ($order->getStatus() == '1' || $order->getStatus() == '-1') {
                $order->setStatus('0'); // Reset status to "En cours"
            }

            // Persist the change to the database
            $this->entityManager->flush();
        }

        // Redirect to the orders list or another appropriate page
        return $this->redirectToRoute('app_orders_all');
    }



    #[Route('/orders/delete/{id}', name: 'order_delete')]
    public function delete(int $id, OrdersRepository $orderRepository, EntityManagerInterface $entityManager): Response
    {
        // Fetch the order using the repository
        $order = $orderRepository->find($id);

        if (!$order) {
            // If no order is found, redirect to the orders list with an error message
            $this->addFlash('error', 'Commande introuvable.');

            return $this->redirectToRoute('app_orders_my_orders');
        }

        // Optional: Add additional checks to ensure the user can delete this order
        // For example:
        // if ($this->getUser() !== $order->getUser()) {
        //     throw $this->createAccessDeniedException('You cannot delete this order.');
        // }

        // Use the EntityManager to remove the order
        $entityManager->remove($order);
        $entityManager->flush();

        // Flash message to notify the user
        $this->addFlash('success', 'Commande supprimée avec succès.');

        // Redirect to the order list page
        return $this->redirectToRoute('app_orders_my_orders');
    }

}

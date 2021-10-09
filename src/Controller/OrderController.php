<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Repository\OrderRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Serializer;

/**
  * Class OrderController
  * @package App\Controller
  * @Route("/api", name="order_api_")
  */
class OrderController extends AbstractController
{
    /**
     * @param Request $request
     * @param OrderRepository $orderRepository
     * @return JsonResponse
     * @Route("/orders", name="get_orders", methods={"GET"})
     */
    public function getOrders(Request $request, OrderRepository $orderRepository)
    {      
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $orders = $this->getDoctrine()
                ->getRepository(Order::class)
                ->findBy(
                    ['user' => $user]
                );

        $data = [];
        foreach ($orders as $order) {
            array_push($data, [
                "id" => $order->getId(),
                "product" => [
                    "id" => $order->getProduct()->getId(),
                    "name" => $order->getProduct()->getName(),
                    "price" => $order->getProduct()->getPrice(),
                ],
                "order_code" => $order->getOrderCode(),
                "quantity" => $order->getQuantity(),
                "address" => $order->getAddress(),
                "shipping_date" => $order->getShippingDate(),
            ]);
        }

        return $this->response([
            'status' => 200,
            'data' => $data,
        ], 200);
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param OrderRepository $orderRepository
     * @return JsonResponse
     * @Route("/orders", name="create_order", methods={"POST"})
     */
    public function createOrder(Request $request, EntityManagerInterface $entityManager, OrderRepository $orderRepository)
    {
        try{
            $request = $this->transformJsonBody($request);
        
            if (!$request || !$request->get('product_id') || !$request->get('quantity') || !$request->get('address')) {
                return $this->response([
                    "status" => 400,
                    "message" => "Tüm alanların dolu olduğundan emin olun."
                ], $status = 400);
            }

            $product = $this->getDoctrine()
                ->getRepository(Product::class)
                ->find($request->get('product_id'));

            if (!$product) {
              return $this->response([
                  "status" => 404,
                  "message" => "Almak istediğiniz ürün bulunamadı."
              ], $status = 404);
            }

            $user = $this->get('security.token_storage')->getToken()->getUser();

            $order = new Order();
            $order->setProduct($product);
            $order->setUser($user);
            $order->setOrderCode($this->generateRandomString());
            $order->setQuantity($request->get('quantity'));
            $order->setAddress($request->get('address'));
            $entityManager->persist($order);
            $entityManager->flush();

            return $this->response([
                'status' => 201,
                'message' => "Sipariş oluşturuldu.",
            ], 201);
        
        } catch (\Exception $e) {
            return $this->response([
                'status' => 500,
                'message' => "Beklenmeyen bir meydana geldi.",
            ], 500);
        }       
    }

    /**
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param OrderRepository $orderRepository
     * @param $id
     * @return JsonResponse
     * @Route("/orders/{id}", name="update_order", methods={"PUT"})
     */
    public function updateOrder(Request $request, EntityManagerInterface $entityManager, OrderRepository $orderRepository, $id)
    {
        try{
            $order = $orderRepository->find($id);

            if (!$order) {
                return $this->response([
                    "status" => 404,
                    "message" => "Sipariş bulunamadı."
                ], $status = 404);
            }

            $user = $this->get('security.token_storage')->getToken()->getUser();

            if ($user !== $order->getUser()) {
                return $this->response([
                    "status" => 403,
                    "message" => "Yalnızca kendi siparişlerinizi güncelleyebilirsiniz."
                ], $status = 403);
            }

            if (!is_null($order->getShippingDate())) {
                return $this->response([
                    "status" => 403,
                    "message" => "Sevkiyat tarihi belirlenmiş bir siparişi güncelleyemezsiniz."
                ], $status = 403);
            }

            $request = $this->transformJsonBody($request);

            if (!$request || !$request->get('quantity') || !$request->get('address') || !$request->get('shipping_date')) {
                return $this->response([
                    "status" => 400,
                    "message" => "Tüm alanların dolu olduğundan emin olun."
                ], $status = 400);
            }
            
            $order->setQuantity($request->get('quantity'));
            $order->setAddress($request->get('address'));
            $order->setShippingDate(\DateTime::createFromFormat('Y-m-d', $request->get('shipping_date')));
            $entityManager->persist($order);
            $entityManager->flush();

            return $this->response([
                'status' => 200,
                'message' => "Sipariş güncellendi.",
            ], 200);
        
        } catch (\Exception $e) {
            return $this->response([
                'status' => 500,
                'message' => "Beklenmeyen bir meydana geldi.",
            ], 500);
        }       
    }

    /**
     * @param Request $request
     * @param OrderRepository $orderRepository
     * @param $id
     * @return JsonResponse
     * @Route("/orders/{id}", name="show_order", methods={"GET"})
     */
    public function showOrder(Request $request, OrderRepository $orderRepository, $id)
    {
        try{
            $order = $orderRepository->find($id);

            if (!$order) {
                return $this->response([
                    "status" => 404,
                    "message" => "Sipariş bulunamadı."
                ], $status = 404);
            }

            $user = $this->get('security.token_storage')->getToken()->getUser();

            if ($user !== $order->getUser()) {
                return $this->response([
                    "status" => 403,
                    "message" => "Yalnızca kendi siparişlerinizi görüntüleyebilirsiniz."
                ], $status = 403);
            }

            $data = [
                "id" => $order->getId(),
                "product" => [
                    "id" => $order->getProduct()->getId(),
                    "name" => $order->getProduct()->getName(),
                    "price" => $order->getProduct()->getPrice(),
                ],
                "order_code" => $order->getOrderCode(),
                "quantity" => $order->getQuantity(),
                "address" => $order->getAddress(),
                "shipping_date" => $order->getShippingDate(),
            ];

            return $this->response([
                'status' => 200,
                'data' => $data,
            ], 200);
        
        } catch (\Exception $e) {
            return $this->response([
                'status' => 500,
                'message' => "Beklenmeyen bir meydana geldi.",
            ], 500);
        }       
    }

    /**
     * Returns a JSON response
     *
     * @param array $data
     * @param $status
     * @param array $headers
     * @return JsonResponse
     */
    public function response($data, $status = 200, $headers = []) 
    {
        return new JsonResponse($data, $status, $headers);
    }

    protected function transformJsonBody(\Symfony\Component\HttpFoundation\Request $request) {
        $data = json_decode($request->getContent(), true);

        if ($data === null) {
          return $request;
        }

        $request->request->replace($data);

        return $request;
    }

    public function generateRandomString($length = 32, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
 
}
<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Service\ProductService;
use App\Domain\ValueObject\ProductId;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

final class ProductController extends AbstractController
{
    public function __construct(
        private ProductService $productService,
        private SerializerInterface $serializer,
    ) {
    }

    #[Route('/product/{id}', name: 'product_detail', methods: ['GET'])]
    public function detail(string $id, Request $request): Response
    {
        $productId = ProductId::fromString($id);
        $startTime = microtime(true);

        $result = $this->productService->getProductWithTrace($productId);
        $ttfbMs = round((microtime(true) - $startTime) * 1000, 2);

        if ($request->headers->has('Turbo-Frame')) {
            return $this->render('product/_frame.html.twig', [
                'product' => $result->product,
                'trace' => $result,
                'ttfbMs' => $ttfbMs,
            ]);
        }

        $json = $this->serializer->serialize($result->product, 'json');

        return JsonResponse::fromJsonString($json);
    }

    #[Route('/product/{id}/counter', name: 'product_counter', methods: ['GET'])]
    public function counter(string $id): Response
    {
        $productId = ProductId::fromString($id);
        $count = $this->productService->getCount($productId);

        return new JsonResponse(['count' => $count]);
    }
}

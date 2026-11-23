<?php

namespace App\Http\Controllers;

use App\Services\ThermalReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PrintBridgeController extends Controller
{
    private const MAX_LINES = 300;

    private const MAX_LINE_LENGTH = 500;

    public function __invoke(Request $request, ThermalReceiptPrinter $printer): JsonResponse|Response
    {
        $origin = (string) $request->headers->get('Origin', '');

        if (! $this->isLoopback((string) $request->ip()) || ! $this->isAllowedOrigin($origin)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }

        if ($request->isMethod('OPTIONS')) {
            return $this->withCors(response()->noContent(), $origin);
        }

        if (! ThermalReceiptPrinter::isEnabled()) {
            return $this->withCors(response()->json(['success' => false, 'message' => 'Direct thermal printing is turned off on this PC.'], 409), $origin);
        }

        $validator = Validator::make($request->all(), [
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.text' => ['present', 'nullable', 'string', 'max:'.self::MAX_LINE_LENGTH],
            'lines.*.center' => ['required', 'boolean'],
            'lines.*.bold' => ['required', 'boolean'],
            'lines.*.tall' => ['required', 'boolean'],
            'lines.*.qr' => ['sometimes', 'nullable', 'string', 'max:64'],
            'open_drawer' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->withCors(response()->json(['success' => false, 'message' => 'Invalid receipt.'], 422), $origin);
        }

        $lines = array_map(fn (array $line): array => [
            'text' => (string) preg_replace('/[^\x20-\x7E\n]/', '', (string) $line['text']),
            'center' => (bool) $line['center'],
            'bold' => (bool) $line['bold'],
            'tall' => (bool) $line['tall'],
            'qr' => (string) preg_replace('/[^\x20-\x7E]/', '', (string) ($line['qr'] ?? '')),
        ], $validator->validated()['lines']);

        $openDrawer = config('services.receipt_printer.open_drawer') && $request->boolean('open_drawer');

        try {
            $printer->printLineSpecs($lines, $openDrawer);
        } catch (Throwable $e) {
            Log::warning('Print bridge: thermal printing failed', ['exception' => $e->getMessage()]);

            return $this->withCors(response()->json([
                'success' => false,
                'message' => 'Could not reach the receipt printer. Check that it is on, has paper, and is connected.',
            ], 503), $origin);
        }

        return $this->withCors(response()->json(['success' => true]), $origin);
    }

    private function isLoopback(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'], true);
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $allowed = array_map(
            fn (string $site): string => rtrim($site, '/'),
            array_filter(array_map('trim', explode(',', (string) config('services.receipt_printer.bridge_origins', ''))))
        );

        return $origin !== '' && in_array(rtrim($origin, '/'), $allowed, true);
    }

    private function withCors(JsonResponse|Response $response, string $origin): JsonResponse|Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');
        $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        $response->headers->set('Access-Control-Max-Age', '600');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}

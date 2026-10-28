<?php

namespace App\Http\Controllers\Bioimpedance;

use App\Http\Controllers\Controller;
use App\Models\Bioimpedance\BioimpedanceClinicSetting;
use App\Services\Bioimpedance\BodyscanProImageProcessor;
use App\Services\Bioimpedance\RelaxmedicImageProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AssessmentImportController extends Controller
{
    public function processRelaxmedicImage(Request $request, RelaxmedicImageProcessor $processor): JsonResponse
    {
        abort_unless(
            $request->user()->isClinicalProfessional(),
            403,
            'Apenas profissionais e administradores podem executar esta ação.',
        );

        if (BioimpedanceClinicSetting::current()->scale_model !== BioimpedanceClinicSetting::SCALE_MODEL_RELAXMEDIC) {
            throw ValidationException::withMessages([
                'image' => 'Selecione a balança Relaxmedic nas configurações antes de processar a imagem.',
            ]);
        }

        $image = $this->validatedImage($request);

        try {
            $extraction = $processor->process($image);
        } catch (ConnectionException|RequestException $exception) {
            return $this->externalReadingFailure($exception);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['image' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'extraction' => $extraction,
        ]);
    }

    public function processBodyscanProImage(Request $request, BodyscanProImageProcessor $processor): JsonResponse
    {
        abort_unless(
            $request->user()->isClinicalProfessional(),
            403,
            'Apenas profissionais e administradores podem executar esta ação.',
        );

        if (BioimpedanceClinicSetting::current()->scale_model !== BioimpedanceClinicSetting::SCALE_MODEL_BODYSCAN_PRO) {
            throw ValidationException::withMessages([
                'image' => 'Selecione a balança Bodyscan Pro nas configurações antes de processar a imagem.',
            ]);
        }

        $image = $this->validatedImage($request);

        try {
            $extraction = $processor->process($image);
        } catch (ConnectionException|RequestException $exception) {
            return $this->externalReadingFailure($exception);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['image' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'extraction' => $extraction,
        ]);
    }

    private function validatedImage(Request $request): UploadedFile
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg',
                'extensions:jpg,jpeg',
                'mimetypes:image/jpeg',
                'max:10240',
                'dimensions:min_width=200,min_height=400,max_width=10000,max_height=20000',
            ],
        ], [
            'image.required' => 'Selecione uma imagem JPEG ou JPG.',
            'image.image' => 'O arquivo enviado não é uma imagem válida.',
            'image.mimes' => 'A imagem deve estar no formato JPEG ou JPG.',
            'image.extensions' => 'A extensão do arquivo deve ser JPEG ou JPG.',
            'image.mimetypes' => 'A imagem deve estar no formato JPEG ou JPG.',
            'image.max' => 'A imagem deve ter no máximo 10 MB.',
            'image.dimensions' => 'A imagem possui dimensões incompatíveis com o relatório.',
        ]);

        return $validated['image'];
    }

    private function externalReadingFailure(ConnectionException|RequestException $exception): JsonResponse
    {
        if ($exception instanceof ConnectionException) {
            $timedOut = str_contains(mb_strtolower($exception->getMessage()), 'timed out');

            return response()->json([
                'message' => $timedOut
                    ? 'A leitura do relatório excedeu o tempo esperado. A imagem não foi salva; tente novamente.'
                    : 'Não foi possível acessar o serviço de leitura da imagem. Tente novamente.',
            ], $timedOut ? 504 : 502);
        }

        return response()->json([
            'message' => 'O serviço de leitura recusou o processamento da imagem. Tente novamente em alguns instantes.',
        ], 502);
    }
}

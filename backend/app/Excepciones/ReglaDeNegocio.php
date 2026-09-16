<?php

namespace App\Excepciones;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Una regla del torneo que no se cumple: inscribirse con el cupo lleno, cargar
 * un resultado en un partido cancelado, pagar una orden vencida.
 *
 * No es un error del programa ni un fallo de validación de formato, así que
 * viaja al cliente como un mensaje en español y un código 422, con la misma
 * forma que los errores de validación para que el frontend los muestre igual.
 */
class ReglaDeNegocio extends Exception
{
    public function __construct(
        string $mensaje,
        private readonly int $codigoHttp = 422,
        private readonly ?string $campo = null,
    ) {
        parent::__construct($mensaje);
    }

    public static function porqueSi(string $mensaje, ?string $campo = null): self
    {
        return new self($mensaje, 422, $campo);
    }

    public function render(Request $peticion): JsonResponse
    {
        $cuerpo = ['message' => $this->getMessage()];

        if ($this->campo !== null) {
            $cuerpo['errors'] = [$this->campo => [$this->getMessage()]];
        }

        return response()->json($cuerpo, $this->codigoHttp);
    }
}

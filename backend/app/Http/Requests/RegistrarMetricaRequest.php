<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Solo categorías cerradas: ninguna URL, identidad o texto libre llega al almacén. */
class RegistrarMetricaRequest extends FormRequest
{
    public function rules(): array
    {
        $maximo = $this->input('tipo') === 'cls' ? 100 : 600000;

        return [
            'id' => ['required', 'uuid'],
            'sesion' => ['required', 'uuid'],
            'consentimiento' => ['required', 'accepted'],
            'tipo' => ['required', Rule::in(['pagina', 'lcp', 'cls', 'inp'])],
            'pagina' => ['required', Rule::in(config('metricas.paginas'))],
            'dispositivo' => ['required', Rule::in(['movil', 'tableta', 'escritorio'])],
            'valor' => ['exclude_if:tipo,pagina', 'required_unless:tipo,pagina', 'numeric', 'min:0', 'max:'.$maximo],
        ];
    }
}

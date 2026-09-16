<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación
|--------------------------------------------------------------------------
|
| Laravel trae sus mensajes en inglés. Estos son los mismos, en español, y son
| los que el frontend le muestra a la persona que está llenando el formulario.
| Los :atributos se reemplazan solos con el nombre que declare cada FormRequest
| en su método attributes().
|
*/

return [
    'accepted' => 'Debes aceptar :attribute.',
    'active_url' => ':attribute no es una URL válida.',
    'after' => ':attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => ':attribute solo puede contener letras.',
    'alpha_dash' => ':attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => ':attribute solo puede contener letras y números.',
    'array' => ':attribute debe ser una lista.',
    'before' => ':attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => ':attribute debe tener entre :min y :max elementos.',
        'file' => ':attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => ':attribute debe estar entre :min y :max.',
        'string' => ':attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => ':attribute no es una fecha válida.',
    'date_equals' => ':attribute debe ser una fecha igual a :date.',
    'date_format' => ':attribute no corresponde al formato :format.',
    'decimal' => ':attribute debe tener :decimal decimales.',
    'declined' => ':attribute debe ser rechazado.',
    'different' => ':attribute y :other deben ser diferentes.',
    'digits' => ':attribute debe tener :digits dígitos.',
    'digits_between' => ':attribute debe tener entre :min y :max dígitos.',
    'dimensions' => 'Las dimensiones de la imagen :attribute no son válidas.',
    'distinct' => 'El campo :attribute tiene un valor duplicado.',
    'doesnt_end_with' => ':attribute no puede terminar con: :values.',
    'doesnt_start_with' => ':attribute no puede comenzar con: :values.',
    'email' => ':attribute debe ser un correo electrónico válido.',
    'ends_with' => ':attribute debe terminar con: :values.',
    'enum' => 'El valor de :attribute no es válido.',
    'exists' => 'El valor de :attribute no existe.',
    'file' => ':attribute debe ser un archivo.',
    'filled' => 'El campo :attribute no puede estar vacío.',
    'gt' => [
        'array' => ':attribute debe tener más de :value elementos.',
        'file' => ':attribute debe pesar más de :value kilobytes.',
        'numeric' => ':attribute debe ser mayor que :value.',
        'string' => ':attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'array' => ':attribute debe tener :value elementos o más.',
        'file' => ':attribute debe pesar :value kilobytes o más.',
        'numeric' => ':attribute debe ser mayor o igual que :value.',
        'string' => ':attribute debe tener :value caracteres o más.',
    ],
    'image' => ':attribute debe ser una imagen.',
    'in' => 'El valor de :attribute no es válido.',
    'in_array' => 'El campo :attribute no existe en :other.',
    'integer' => ':attribute debe ser un número entero.',
    'ip' => ':attribute debe ser una dirección IP válida.',
    'ipv4' => ':attribute debe ser una dirección IPv4 válida.',
    'ipv6' => ':attribute debe ser una dirección IPv6 válida.',
    'json' => ':attribute debe ser una cadena JSON válida.',
    'lowercase' => ':attribute debe estar en minúsculas.',
    'lt' => [
        'array' => ':attribute debe tener menos de :value elementos.',
        'file' => ':attribute debe pesar menos de :value kilobytes.',
        'numeric' => ':attribute debe ser menor que :value.',
        'string' => ':attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => ':attribute no debe tener más de :value elementos.',
        'file' => ':attribute debe pesar :value kilobytes o menos.',
        'numeric' => ':attribute debe ser menor o igual que :value.',
        'string' => ':attribute debe tener :value caracteres o menos.',
    ],
    'max' => [
        'array' => ':attribute no debe tener más de :max elementos.',
        'file' => ':attribute no debe pesar más de :max kilobytes.',
        'numeric' => ':attribute no debe ser mayor que :max.',
        'string' => ':attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => ':attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => ':attribute debe tener al menos :min elementos.',
        'file' => ':attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':attribute debe ser al menos :min.',
        'string' => ':attribute debe tener al menos :min caracteres.',
    ],
    'multiple_of' => ':attribute debe ser múltiplo de :value.',
    'not_in' => 'El valor de :attribute no es válido.',
    'not_regex' => 'El formato de :attribute no es válido.',
    'numeric' => ':attribute debe ser un número.',
    'password' => [
        'letters' => 'La contraseña debe contener al menos una letra.',
        'mixed' => 'La contraseña debe contener al menos una mayúscula y una minúscula.',
        'numbers' => 'La contraseña debe contener al menos un número.',
        'symbols' => 'La contraseña debe contener al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Elige otra.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'prohibits' => 'El campo :attribute no permite que :other esté presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => ':attribute es obligatorio.',
    'required_if' => ':attribute es obligatorio cuando :other es :value.',
    'required_unless' => ':attribute es obligatorio a menos que :other esté en :values.',
    'required_with' => ':attribute es obligatorio cuando :values está presente.',
    'required_without' => ':attribute es obligatorio cuando :values no está presente.',
    'same' => ':attribute y :other deben coincidir.',
    'size' => [
        'array' => ':attribute debe contener :size elementos.',
        'file' => ':attribute debe pesar :size kilobytes.',
        'numeric' => ':attribute debe ser :size.',
        'string' => ':attribute debe tener :size caracteres.',
    ],
    'starts_with' => ':attribute debe comenzar con: :values.',
    'string' => ':attribute debe ser texto.',
    'timezone' => ':attribute debe ser una zona horaria válida.',
    'unique' => 'Ese valor de :attribute ya está en uso.',
    'uploaded' => 'No se pudo subir :attribute.',
    'uppercase' => ':attribute debe estar en mayúsculas.',
    'url' => ':attribute debe ser una URL válida.',
    'uuid' => ':attribute debe ser un UUID válido.',

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'mensaje personalizado',
        ],
    ],

    'attributes' => [
        'nombre' => 'nombre',
        'correo' => 'correo electrónico',
        'clave' => 'contraseña',
        'telefono' => 'teléfono',
        'imagen' => 'imagen',
        'foto' => 'foto',
    ],
];

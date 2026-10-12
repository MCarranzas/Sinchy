<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validacion (espanol)
|--------------------------------------------------------------------------
|
| Laravel resuelve los mensajes con `lang/{locale}/validation.php`. Con
| APP_LOCALE=es y este archivo presente, TODOS los mensajes de validacion de
| la API salen en espanol, incluidos los de reglas que no escriben mensaje a
| mano (required, exists, regex, decimal...).
|
| `attributes` traduce el nombre tecnico del campo (fecha_transaccion) al
| texto que ve el usuario (fecha de transaccion). Sin este bloque, aun con
| el archivo en espanol, las reglas que interpolan :attribute mostrarian el
| nombre crudo del input.
|
*/

return [

    'accepted' => 'El campo :attribute debe ser aceptado.',
    'accepted_if' => 'El campo :attribute debe ser aceptado cuando :other sea :value.',
    'active_url' => 'El campo :attribute debe ser una URL valida.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => 'El campo :attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, numeros, guiones y guiones bajos.',
    'alpha_numeric' => 'El campo :attribute solo puede contener letras y numeros.',
    'alpha_numeric_dash' => 'El campo :attribute solo puede contener letras, numeros, guiones y guiones bajos.',
    'alpha_numeric_space' => 'El campo :attribute solo puede contener letras, numeros y espacios.',
    'alpha_spaces' => 'El campo :attribute solo puede contener letras y espacios.',
    'array' => 'El campo :attribute debe ser una lista.',
    'ascii' => 'El campo :attribute solo puede contener caracteres alfanumericos y simbolos de un solo byte.',
    'before' => 'El campo :attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => 'El campo :attribute debe tener entre :min y :max elementos.',
        'file' => 'El archivo :attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmacion de :attribute no coincide.',
    'country' => 'El campo :attribute no es un pais valido.',
    'current_password' => 'La contrasena es incorrecta.',
    'date' => 'El campo :attribute debe ser una fecha valida.',
    'date_equals' => 'El campo :attribute debe ser una fecha igual a :date.',
    'date_format' => 'El campo :attribute debe tener el formato :format.',
    'decimal' => 'El campo :attribute debe tener :decimal cifras decimales.',
    'declined' => 'El campo :attribute debe ser rechazado.',
    'declined_if' => 'El campo :attribute debe ser rechazado cuando :other sea :value.',
    'different' => 'El campo :attribute y :other deben ser diferentes.',
    'digits' => 'El campo :attribute debe tener :digits digitos.',
    'digits_between' => 'El campo :attribute debe tener entre :min y :max digitos.',
    'dimensions' => 'La imagen :attribute tiene dimensiones no validas.',
    'distinct' => 'El campo :attribute tiene un valor duplicado.',
    'doesnt_end_with' => 'El campo :attribute no puede terminar en uno de los siguientes valores: :values.',
    'doesnt_start_with' => 'El campo :attribute no puede empezar con uno de los siguientes valores: :values.',
    'email' => 'El campo :attribute debe ser una direccion de correo valida.',
    'ends_with' => 'El campo :attribute debe terminar en uno de los siguientes valores: :values.',
    'enum' => 'El valor seleccionado para :attribute no es valido.',
    'exists' => 'El valor seleccionado para :attribute no existe.',
    'extensions' => 'El campo :attribute debe tener una de las siguientes extensiones: :values.',
    'file' => 'El campo :attribute debe ser un archivo.',
    'filled' => 'El campo :attribute debe tener un valor.',
    'gt' => [
        'array' => 'El campo :attribute debe tener mas de :value elementos.',
        'file' => 'El archivo :attribute debe pesar mas de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser mayor que :value.',
        'string' => 'El campo :attribute debe tener mas de :value caracteres.',
    ],
    'gte' => [
        'array' => 'El campo :attribute debe tener :value elementos o mas.',
        'file' => 'El archivo :attribute debe pesar :value kilobytes o mas.',
        'numeric' => 'El campo :attribute debe ser mayor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o mas.',
    ],
    'hex_color' => 'El campo :attribute debe ser un color hexadecimal valido.',
    'image' => 'El campo :attribute debe ser una imagen.',
    'in' => 'El valor seleccionado para :attribute no es valido.',
    'in_array' => 'El campo :attribute debe existir en :other.',
    'integer' => 'El campo :attribute debe ser un numero entero.',
    'ip' => 'El campo :attribute debe ser una direccion IP valida.',
    'ipv4' => 'El campo :attribute debe ser una direccion IPv4 valida.',
    'ipv6' => 'El campo :attribute debe ser una direccion IPv6 valida.',
    'json' => 'El campo :attribute debe ser una cadena JSON valida.',
    'list' => 'El campo :attribute debe ser una lista.',
    'lowercase' => 'El campo :attribute debe estar en minusculas.',
    'lt' => [
        'array' => 'El campo :attribute debe tener menos de :value elementos.',
        'file' => 'El archivo :attribute debe pesar menos de :value kilobytes.',
        'numeric' => 'El campo :attribute debe ser menor que :value.',
        'string' => 'El campo :attribute debe tener menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'El campo :attribute debe tener :value elementos o menos.',
        'file' => 'El archivo :attribute debe pesar :value kilobytes o menos.',
        'numeric' => 'El campo :attribute debe ser menor o igual que :value.',
        'string' => 'El campo :attribute debe tener :value caracteres o menos.',
    ],
    'mac_address' => 'El campo :attribute debe ser una direccion MAC valida.',
    'max' => [
        'array' => 'El campo :attribute no debe tener mas de :max elementos.',
        'file' => 'El archivo :attribute no debe pesar mas de :max kilobytes.',
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener mas de :max caracteres.',
    ],
    'max_digits' => 'El campo :attribute no debe tener mas de :max digitos.',
    'mimes' => 'El archivo :attribute debe ser de tipo: :values.',
    'mimetypes' => 'El archivo :attribute debe ser de tipo: :values.',
    'min' => [
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
        'file' => 'El archivo :attribute debe pesar al menos :min kilobytes.',
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'min_digits' => 'El campo :attribute debe tener al menos :min digitos.',
    'missing' => 'El campo :attribute no debe estar presente.',
    'missing_if' => 'El campo :attribute no debe estar presente cuando :other sea :value.',
    'missing_unless' => 'El campo :attribute no debe estar presente a menos que :other sea :value.',
    'missing_with' => 'El campo :attribute no debe estar presente cuando :values este presente.',
    'missing_with_all' => 'El campo :attribute no debe estar presente cuando :values esten presentes.',
    'multiple_of' => 'El campo :attribute debe ser multiplo de :value.',
    'not_in' => 'El valor seleccionado para :attribute no es valido.',
    'not_regex' => 'El formato del campo :attribute no es valido.',
    'numeric' => 'El campo :attribute debe ser un numero.',
    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una letra mayuscula y una minuscula.',
        'numbers' => 'El campo :attribute debe contener al menos un numero.',
        'symbols' => 'El campo :attribute debe contener al menos un simbolo.',
        'uncompromised' => 'El :attribute indicado aparecio en una filtracion de datos. Por favor elija otro :attribute.',
    ],
    'present' => 'El campo :attribute debe estar presente.',
    'present_if' => 'El campo :attribute debe estar presente cuando :other sea :value.',
    'present_unless' => 'El campo :attribute debe estar presente a menos que :other sea :value.',
    'present_with' => 'El campo :attribute debe estar presente cuando :values este presente.',
    'present_with_all' => 'El campo :attribute debe estar presente cuando :values esten presentes.',
    'prohibited' => 'El campo :attribute esta prohibido.',
    'prohibited_if' => 'El campo :attribute esta prohibido cuando :other sea :value.',
    'prohibited_if_accepted' => 'El campo :attribute esta prohibido cuando :other sea aceptado.',
    'prohibited_if_declined' => 'El campo :attribute esta prohibido cuando :other sea rechazado.',
    'prohibited_unless' => 'El campo :attribute esta prohibido a menos que :other sea :value.',
    'prohibits' => 'El campo :attribute prohibe que :other este presente.',
    'regex' => 'El formato del campo :attribute no es valido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_array_keys' => 'El campo :attribute debe incluir entradas para: :values.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other sea :value.',
    'required_if_accepted' => 'El campo :attribute es obligatorio cuando :other sea aceptado.',
    'required_if_declined' => 'El campo :attribute es obligatorio cuando :other sea rechazado.',
    'required_unless' => 'El campo :attribute es obligatorio a menos que :other este en :values.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values este presente.',
    'required_with_all' => 'El campo :attribute es obligatorio cuando :values esten presentes.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no este presente.',
    'required_without_all' => 'El campo :attribute es obligatorio cuando ninguno de :values este presente.',
    'same' => 'El campo :attribute y :other deben coincidir.',
    'size' => [
        'array' => 'El campo :attribute debe contener :size elementos.',
        'file' => 'El archivo :attribute debe pesar :size kilobytes.',
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe tener :size caracteres.',
    ],
    'starts_with' => 'El campo :attribute debe empezar con uno de los siguientes valores: :values.',
    'string' => 'El campo :attribute debe ser una cadena de texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria valida.',
    'unique' => 'El valor del campo :attribute ya esta en uso.',
    'uploaded' => 'No se pudo subir el archivo :attribute.',
    'uppercase' => 'El campo :attribute debe estar en mayusculas.',
    'url' => 'El campo :attribute debe ser una URL valida.',
    'ulid' => 'El campo :attribute debe ser un ULID valido.',
    'uuid' => 'El campo :attribute debe ser un UUID valido.',

    // Sufijo del mensaje resumido del 422 cuando hay varios errores.
    'error' => '(y :count error mas)',
    'errors' => '(y :count errores mas)',

    /*
    |--------------------------------------------------------------------------
    | Mensajes personalizados por regla
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'password' => [
            'confirmed' => 'La confirmacion de la nueva contrasena no coincide.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nombres legibles de los campos (EPCORO)
    |--------------------------------------------------------------------------
    |
    | Traduce el nombre tecnico del input al texto que se muestra en el 422.
    | Si un campo no esta aqui, se muestra su nombre crudo (snake_case).
    |
    */

    'attributes' => [
        // documento
        'numero_documento' => 'numero de documento',
        'fecha_transaccion' => 'fecha de transaccion',
        'observaciones' => 'observaciones',
        'comentario' => 'comentario',
        'motivo' => 'motivo',
        'estado_destino' => 'estado de destino',

        // insumos
        'cotizacion_usd_oz_t' => 'cotizacion (USD por onza troy)',
        'cotizacion_id' => 'cotizacion quincenal',
        'aplica_caja_salud' => 'aplicacion de caja de salud',
        'peso_gramos' => 'peso en gramos',
        'ley_pagable' => 'ley pagable',

        // comprador
        'comprador_id' => 'comprador',
        'comprador_razon_social' => 'razon social del comprador',
        'comprador_nim' => 'NIM del comprador',
        'comprador_nit' => 'NIT del comprador',

        // vendedor
        'vendedor_persona_id' => 'vendedor',
        'vendedor_ci' => 'CI del vendedor',

        // procedencia
        'area_minera_id' => 'area minera',
        'departamento_id' => 'departamento',
        'provincia_id' => 'provincia',
        'municipio_id' => 'municipio',
        'localidad_id' => 'localidad',
        'codigo_municipio' => 'codigo de municipio',
        'codigo_ajam_patente' => 'codigo AJAM / patente',

        // presentacion
        'presentacion_mineral_id' => 'presentacion del mineral',

        // personas (alta rapida)
        'nombres' => 'nombres',
        'apellidos' => 'apellidos',
        'nombre_completo' => 'nombre completo',
        'ci' => 'CI',
        'nit' => 'NIT',
        'tipo_documento' => 'tipo de documento',
        'email' => 'correo electronico',
        'telefono' => 'telefono',
        'direccion' => 'direccion',

        // sesion y contrasena
        'username' => 'usuario',
        'password' => 'contrasena',
        'password_actual' => 'contrasena actual',
        'password_confirmation' => 'confirmacion de contrasena',

        // parametros
        'parametros' => 'parametros',
        'parametros.*' => 'valor del parametro',

        // catalogos
        'q' => 'texto de busqueda',
    ],

];

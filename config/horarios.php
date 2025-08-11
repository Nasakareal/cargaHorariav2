<?php
return [
  'disponibles' => [
    'MATUTINO' => [
      'Lunes'     => ['start'=>'07:00:00','end'=>'15:00:00'],
      'Martes'    => ['start'=>'07:00:00','end'=>'15:00:00'],
      'Miércoles' => ['start'=>'07:00:00','end'=>'14:00:00'],
      'Jueves'    => ['start'=>'07:00:00','end'=>'15:00:00'],
      'Viernes'   => ['start'=>'07:00:00','end'=>'14:00:00'],
    ],
    'VESPERTINO' => [
      'Lunes'     => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Martes'    => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Miércoles' => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Jueves'    => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Viernes'   => ['start'=>'12:00:00','end'=>'19:00:00'],
    ],
    // con múltiples slots el mismo día:
    'MIXTO' => [
      'Lunes'     => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Martes'    => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Miércoles' => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Jueves'    => ['start'=>'12:00:00','end'=>'19:00:00'],
      'Viernes'   => [
        ['start'=>'12:00:00','end'=>'19:00:00'],
        ['start'=>'16:00:00','end'=>'20:00:00'],
      ],
      'Sábado'    => ['start'=>'07:00:00','end'=>'18:00:00'],
    ],
    'ZINAPÉCUARO' => [
      'Viernes' => [
        ['start'=>'12:00:00','end'=>'19:00:00'],
        ['start'=>'16:00:00','end'=>'20:00:00'],
      ],
      'Sábado'  => ['start'=>'07:00:00','end'=>'18:00:00'],
    ],
    'ENFERMERIA' => [
      'Lunes'     => ['start'=>'07:00:00','end'=>'17:00:00'],
      'Martes'    => ['start'=>'07:00:00','end'=>'17:00:00'],
      'Miércoles' => ['start'=>'07:00:00','end'=>'17:00:00'],
      'Jueves'    => ['start'=>'07:00:00','end'=>'17:00:00'],
      'Viernes'   => ['start'=>'07:00:00','end'=>'17:00:00'],
    ],
    'MATUTINO AVANZADO' => [
      'Lunes'     => ['start'=>'07:00:00','end'=>'12:00:00'],
      'Martes'    => ['start'=>'07:00:00','end'=>'12:00:00'],
      'Miércoles' => ['start'=>'07:00:00','end'=>'12:00:00'],
      'Jueves'    => ['start'=>'07:00:00','end'=>'12:00:00'],
      'Viernes'   => ['start'=>'07:00:00','end'=>'13:00:00'],
    ],
    'VESPERTINO AVANZADO' => [
      'Lunes'     => ['start'=>'12:00:00','end'=>'17:00:00'],
      'Martes'    => ['start'=>'12:00:00','end'=>'17:00:00'],
      'Miércoles' => ['start'=>'12:00:00','end'=>'17:00:00'],
      'Jueves'    => ['start'=>'12:00:00','end'=>'17:00:00'],
      'Viernes'   => ['start'=>'12:00:00','end'=>'17:00:00'],
    ],
  ],

  'dias_semana' => [
    'MATUTINO'            => ['Lunes','Martes','Miércoles','Jueves','Viernes'],
    'VESPERTINO'          => ['Lunes','Martes','Miércoles','Jueves','Viernes'],
    'MIXTO'               => ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'],
    'ZINAPÉCUARO'         => ['Viernes','Sábado'],
    'ENFERMERIA'          => ['Lunes','Martes','Miércoles','Jueves','Viernes'],
    'MATUTINO AVANZADO'   => ['Lunes','Martes','Miércoles','Jueves','Viernes'],
    'VESPERTINO AVANZADO' => ['Lunes','Martes','Miércoles','Jueves','Viernes'],
  ],
];

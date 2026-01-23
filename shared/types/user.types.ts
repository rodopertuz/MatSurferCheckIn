// Tipos TypeScript compartidos

export type EstadoUsuario = 'activo' | 'inactivo' | 'pendiente' | 'congelado' | 'saldobajo';

export type Rol = 'superadmin' | 'admin' | 'coach' | 'frontdesk';

export interface Usuario {
  id: number;
  nombre: string;
  nombre_tabla: string;
  edad: number;
  plan: string;
  foto: string;
  grado: string;
  estado: EstadoUsuario;
  saldo_clases?: string;
  saldo_clases_personalizadas?: string;
  clases_usuario?: string[];
  roles?: string;
  gimnasio_id?: number; // Futuro multi-gimnasio
  _fotoIndex?: number;
}

export interface Clase {
  disciplina: string;
  grupo: string;
  start: string;
  end: string;
  grupos_permitidos?: string[];
}

export interface OndeckResponse {
  ondeck: string[];
  prospectos_ondeck: string[];
  personalizadas_ondeck: string[];
  ondeck_proximas: string[];
  foto_prospecto: string;
  cambios_pendientes: boolean;
}
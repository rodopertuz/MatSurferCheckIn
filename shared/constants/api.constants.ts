// Constantes de API compartidas entre mobile y futuras apps web

export const API_CONFIG = {
  BASE_URL: 'https://matsurfer.co/api',
  TOKEN: 'Bearer ElArtesuave2023',
  TIMEOUT: 30000, // 30 segundos
};

export const API_ENDPOINTS = {
  USUARIOS: '/api.php?action=usuarios',
  ONDECK: '/api.php?action=ondeck',
  CHECKIN: '/api.php?action=check-in',
  GIMNASIOS: '/api.php?action=gimnasios', // Futuro
  MEMBRESIAS: '/api.php?action=membresias', // Futuro
};

export const ESTADOS_USUARIO = {
  ACTIVO: 'activo',
  INACTIVO: 'inactivo',
  PENDIENTE: 'pendiente',
  CONGELADO: 'congelado',
  SALDO_BAJO: 'saldobajo',
} as const;

export const ROLES = {
  SUPERADMIN: 'superadmin',
  ADMIN: 'admin',
  COACH: 'coach',
  FRONTDESK: 'frontdesk',
} as const;
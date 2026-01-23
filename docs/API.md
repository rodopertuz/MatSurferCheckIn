# MatSurfer API Documentation

## Base URL
```
https://matsurfer.co/api
```

## Authentication
Todas las peticiones requieren header:
```
Authorization: Bearer ElArtesuave2023
```

## Endpoints

### GET `/api.php?action=usuarios`
Obtiene lista de usuarios con clases disponibles.

**Response:**
```json
{
  "usuarios": [
    {
      "id": 1,
      "nombre": "Juan Pérez",
      "estado": "activo",
      "clases_usuario": ["BJJ", "Boxeo"]
    }
  ],
  "fotos": ["https://..."],
  "logo_url": "https://...",
  "clases_ahora": {
    "actual": {...},
    "proximas": [...]
  }
}
```

### GET `/api.php?action=ondeck`
Consulta usuarios en espera para check-in.

**Response:**
```json
{
  "ondeck": ["usuario1", "usuario2"],
  "prospectos_ondeck": ["prospecto1"],
  "personalizadas_ondeck": ["usuario3"],
  "ondeck_proximas": ["usuario4"],
  "cambios_pendientes": false
}
```

### POST `/api.php?action=check-in`
Registra check-in de usuario.

**Body:**
```json
{
  "nombre_tabla": "usuario1",
  "clases": [
    {
      "tipo": "actual",
      "disciplina": "BJJ",
      "grupo": "Adults",
      "start": "18:00",
      "end": "19:00"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Check-in registrado"
}
```
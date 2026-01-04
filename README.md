# MatSurfer Check-In App - Documentación Técnica

## Descripción General
Aplicación React Native para gestión de check-in de alumnos en clases de Satori Jiu-Jitsu. Permite visualizar usuarios, clases disponibles y registrar asistencia.

## API
**Base URL**: `https://www.satorijiujitsu.com.co/api/api.php`  
**Authorization**: `Bearer ElArtesuave2023`

### Endpoints

#### 1. GET `?action=usuarios`
Obtiene listado de usuarios y clases disponibles.

**Respuesta**:
```json
{
  "usuarios": [...],
  "fotos": [...],
  "logo_url": "...",
  "clases_ahora": {
    "actual": {
      "disciplina": "...",
      "grupo": "...",
      "start": "...",
      "end": "..."
    },
    "proximas": [
      {
        "disciplina": "...",
        "grupo": "...",
        "start": "...",
        "end": "..."
      }
    ]
  },
  "clases_usuario": [[...], [...]]
}
```

#### 2. GET `?action=ondeck`
Consulta estado de clases en curso (actualización cada 5 segundos).

#### 3. POST `?action=check-in`
Registra check-in de usuario en clases seleccionadas.

**Body**:
```json
{
  "nombre_tabla": "...",
  "clases": [
    {
      "tipo": "actual|proxima|personalizada",
      "disciplina": "...",
      "grupo": "...",
      "start": "...",
      "end": "...",
      "hora": "..." (solo para personalizada)
    }
  ]
}
```

**Respuesta exitosa**:
```json
{
  "success": true
}
```

**Respuesta con error**:
```json
{
  "error": "Descripción del error"
}
```

## Lógica de Filtros

### 1. Filtro por Grupo de Edad
Determina qué clases puede tomar un usuario según su edad:

- **Edad >= 18**: Solo clases de adultos (grupo contiene "adult")
- **Edad >= 11 y < 18**: Solo clases de teens (grupo contiene "teen")
- **Edad < 11**: Solo clases de kids (grupo contiene "kid")
- **Edad = 0 o "" o null o undefined**: **Acceso total** - puede tomar todas las clases

### 2. Filtro por Acceso de Usuario
Determina si el usuario tiene permisos para una clase específica:

- **Coach** (`roles` contiene "coach"): Acceso a todas las clases
- **Acceso Total** (edad = 0 o vacía): Acceso a todas las clases
- **Plan Tiquetera** (`plan` contiene "tiquetera"):
  - Requiere `saldo_clases > 0`
  - Si no hay saldo, no puede acceder
- **Plan Regular**:
  - Requiere que la disciplina esté en el array `clases_usuario[]`
  - Comparación case-insensitive

### 3. Aplicación de Filtros
- **Ambos filtros se aplican simultáneamente** (operación AND lógica)
- Las clases se muestran **SIEMPRE** en la lista del popup
- Los filtros solo afectan el estado habilitado/deshabilitado:
  - Clases habilitadas: `opacity: 1`, `disabled: false`
  - Clases deshabilitadas: `opacity: 0.5`, `disabled: true`
- Una clase está habilitada solo si pasa **ambos** filtros

### 4. Clase Personalizada
- Solo visible para usuarios que **NO** son coach
- Requiere `saldo_clases_personalizadas > 0`
- No tiene disciplina ni grupo asignado
- Se registra con la hora actual

## Estados Principales

```typescript
const [usuarios, setUsuarios] = useState<Usuario[]>([]);
const [fotos, setFotos] = useState<string[]>([]);
const [logoUrl, setLogoUrl] = useState<string>('');
const [clasesAhoraRaw, setClasesAhoraRaw] = useState<any>(null);
const [popupVisible, setPopupVisible] = useState(false);
const [selectedUsuario, setSelectedUsuario] = useState<Usuario | null>(null);
const [selectedClases, setSelectedClases] = useState<string[]>([]);
const [checkinMessage, setCheckinMessage] = useState<string>('');
const [search, setSearch] = useState('');
const [ondeckResult, setOndeckResult] = useState<any>(null);
const [loading, setLoading] = useState(true);
const [error, setError] = useState('');
```

## Tipo Usuario

```typescript
type Usuario = {
  id: number;
  nombre: string;
  nombre_tabla: string;
  edad: number;
  plan: string;
  foto: string;
  grado: string;
  estado: string;
  saldo_clases?: string;
  saldo_clases_personalizadas?: string;
  clases_usuario?: string[];
  roles?: string;
  _fotoIndex?: number; // Índice para vincular con array de fotos
};
```

## Estructura de Clases

### Clase Actual
```typescript
const claseActualObj: {
  disciplina?: string;
  grupo?: string;
  start?: string;
  end?: string;
} | null
```

### Clases Próximas
```typescript
const clasesSiguientesArr: Array<{
  disciplina?: string;
  grupo?: string;
  start?: string;
  end?: string;
}>
```

## Flujo de Check-In

1. **Usuario toca nombre/foto** en menú lateral
2. **Se abre popup** con:
   - Avatar y nombre del usuario
   - Indicadores de saldo: `T [n]` (tiquetera), `P [n]` (personalizada)
   - Estado del usuario
   - Lista de clases disponibles:
     - Personalizada (si aplica)
     - Clase actual (si existe y no es "ninguno")
     - Clases próximas (filtradas, sin "ninguno")
3. **Selección automática**: 
   - Se pre-selecciona la primera clase disponible y habilitada
   - Si no hay clases habilitadas pero hay saldo personalizado, se selecciona "Personalizada"
4. **Usuario puede cambiar selección** usando checkboxes
5. **Al tocar "Aceptar"**:
   - Valida que haya al menos una clase seleccionada
   - Construye array con información completa de clases
   - Envía POST a `?action=check-in`
   - Muestra mensaje de éxito/error
   - Cierra popup tras 1.5 segundos si es exitoso
6. **Al tocar "Cancelar"** o fuera del popup:
   - Cierra popup
   - Limpia selecciones y mensaje
   - Borra búsqueda

## Colores de Estado (Border)

Los usuarios se colorean según su estado:

- **activo**: `green` - Usuario con plan activo y saldo suficiente
- **saldobajo**: `yellow` - Usuario con saldo bajo
- **pendiente**: `orange` - Usuario con pagos pendientes
- **inactivo**: `red` - Usuario sin plan activo
- **congelado**: `#4FC3F7` (celeste) - Usuario con plan congelado
- **default**: `#444` (gris oscuro)

## Funcionalidades Implementadas

### Búsqueda de Usuarios
- Búsqueda parcial por nombre
- Soporta múltiples palabras (todas deben coincidir)
- Case-insensitive
- La búsqueda se mantiene activa al cerrar popup
- Se limpia solo con el botón "Cancelar"

### Refresh
- Pull-to-refresh en lista de usuarios
- Recarga datos completos desde la API
- Animación de carga nativa de React Native

### Actualización Automática
- Consulta `ondeck` cada 5 segundos automáticamente
- Muestra resultados en área principal (JSON crudo actualmente)

### Visualización de Clases
- **Menú superior**: Muestra clase actual y próxima clase
- **Popup**: Lista completa con todas las opciones
- **Filtro "ninguno"**: Se excluyen automáticamente clases con disciplina = "ninguno"

## Estructura de Archivos

```
MatSurfer/
├── App.tsx              # Componente principal con toda la lógica
├── package.json         # Dependencias del proyecto
├── tsconfig.json        # Configuración TypeScript
├── metro.config.js      # Configuración Metro bundler
├── android/             # Proyecto Android nativo
├── ios/                 # Proyecto iOS nativo
└── README.md           # Este archivo
```

## Dependencias Principales

```json
{
  "react": "19.1.1",
  "react-native": "0.82.1",
  "react-native-safe-area-context": "^5.6.1"
}
```

## Estilos Principales

- **Tema**: Dark mode (#222 background, #fff text)
- **Menú lateral**: 300px de ancho, lista scrollable
- **Popup**: Modal centrado con overlay oscuro (z-index: 9999)
- **Avatar**: Circular, 56x56 en lista, 100x100 en popup
- **Checkboxes**: 20x20, azul cuando seleccionado (#2196F3)

## Debugging

### Logs de Desarrollo
Los logs solo se muestran en modo desarrollo:

```typescript
if (nodeEnv === 'development') {
  console.log('[DEBUG] popupVisible:', popupVisible, '| clasesAhoraRaw:', clasesAhoraRaw);
}
```

### Variables de Debug
- `NODE_ENV`: Detectado desde `globalThis.process.env.NODE_ENV`
- `_fotoIndex`: Índice para rastrear fotos por orden original

## Notas Técnicas

- **Vinculación de fotos**: Se usa `_fotoIndex` para mantener correspondencia entre usuarios y array de fotos
- **Overlay del popup**: Usa posicionamiento absoluto con dimensiones completas
- **Manejo de errores**: Captura errores de red y respuestas inválidas de API
- **TypeScript**: Configuración flexible, no estricta
- **Gestión de estado**: Solo hooks de React (useState, useEffect)
- **Sin navegación**: Aplicación de una sola pantalla
- **Filtros en render**: Los filtros se calculan en tiempo real durante el render

## Decisiones de Diseño

1. **Mostrar todas las clases**: Para transparencia, todas las clases se muestran en el popup, aunque estén deshabilitadas
2. **Selección automática**: Mejora UX al pre-seleccionar la primera opción disponible
3. **Edad = 0 como acceso total**: Permite a administradores/coordinadores acceder a cualquier clase
4. **Clases "ninguno" excluidas**: Evita confusión en la UI
5. **Búsqueda persistente**: Facilita check-ins múltiples del mismo grupo
6. **Validación en frontend**: Reduce llamadas innecesarias a la API

## Mejoras Futuras / Pendientes

- [ ] Implementar visualización estructurada de resultado `ondeck`
- [ ] Agregar animaciones a transiciones de popup
- [ ] Optimizar renders con React.memo si es necesario
- [ ] Agregar feedback visual durante carga de check-in (spinner)
- [ ] Implementar manejo de errores más robusto
- [ ] Agregar tests unitarios para lógica de filtros
- [ ] Considerar paginación si la lista de usuarios crece
- [ ] Implementar modo offline con caché local

## Comandos Útiles

```bash
# Instalar dependencias
npm install

# Ejecutar en Android
npx react-native run-android

# Ejecutar en iOS
npx react-native run-ios

# Limpiar caché
npx react-native start --reset-cache
```

## Repositorio
**GitHub**: https://github.com/rodopertuz/MatSurferCheckInV1.0.git

---

**Última actualización**: 4 de enero de 2026

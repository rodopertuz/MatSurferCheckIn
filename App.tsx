/**
 * Sample React Native App
 * https://github.com/facebook/react-native
 *
 * @format
 */

import React, { useEffect, useState, useRef } from 'react';
import { StatusBar, StyleSheet, useColorScheme, View, Text, FlatList, Button, Image, Linking, TouchableOpacity, RefreshControl, TextInput, Keyboard, Animated, Dimensions } from 'react-native';
import {
  SafeAreaProvider,
  useSafeAreaInsets,
} from 'react-native-safe-area-context';
import KeepAwake from '@sayem314/react-native-keep-awake';

function App() {
  const isDarkMode = useColorScheme() === 'dark';

  return (
    <SafeAreaProvider>
      <KeepAwake />
      <StatusBar hidden={true} />
      <AppContent />
    </SafeAreaProvider>
  );
}

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
  _fotoIndex?: number;
};

function AppContent() {

  // Declarar clasesAhoraRaw y setClasesAhoraRaw al inicio
  const [clasesAhoraRaw, setClasesAhoraRaw] = React.useState<any>(null);
  const [popupVisible, setPopupVisible] = React.useState(false);
  const [showPendingWarning, setShowPendingWarning] = React.useState(false);
  const [showInactiveWarning, setShowInactiveWarning] = React.useState(false);
  const [screensaverActive, setScreensaverActive] = React.useState(false);
  const inactivityTimer = useRef<number | null>(null);

  // --- COLOCAR LOS LOGS JUSTO ANTES DEL RETURN ---

  React.useEffect(() => {
    const nodeEnv = typeof (globalThis as any).process !== 'undefined' && (globalThis as any).process.env && (globalThis as any).process.env.NODE_ENV ? (globalThis as any).process.env.NODE_ENV : 'unknown';
    console.log('NODE_ENV:', nodeEnv);
  }, []);

  React.useEffect(() => {
    const nodeEnv = typeof (globalThis as any).process !== 'undefined' && (globalThis as any).process.env && (globalThis as any).process.env.NODE_ENV ? (globalThis as any).process.env.NODE_ENV : 'unknown';
    if (nodeEnv === 'development') {
      console.log('[DEBUG] popupVisible:', popupVisible, '| clasesAhoraRaw:', clasesAhoraRaw);
    }
    if (nodeEnv === 'development' && popupVisible && clasesAhoraRaw) {
      console.log('[DEBUG] clases_ahora (solo cuando popupVisible):', clasesAhoraRaw);
    }
  }, [popupVisible, clasesAhoraRaw]);
  // Estado para el resultado de la consulta ondeck
  const [ondeckResult, setOndeckResult] = useState<any>(null);
  const [ondeckError, setOndeckError] = useState('');
  const [prospectosOndeck, setProspectosOndeck] = useState<string[]>([]);
  const [fotoProspecto, setFotoProspecto] = useState<string>('');

  // Función para consultar la API con método GET y acción 'ondeck'
  const fetchOndeck = async () => {
    setOndeckError('');
    try {
      const response = await fetch('https://www.satorijiujitsu.com.co/api/api.php?action=ondeck', {
        method: 'GET',
        headers: {
          Authorization: 'Bearer ElArtesuave2023',
        },
      });
      const data = await response.json();
      setOndeckResult(data);
      // Guardar prospectos y foto de prospecto
      if (data.prospectos_ondeck && Array.isArray(data.prospectos_ondeck)) {
        setProspectosOndeck(data.prospectos_ondeck);
      } else {
        setProspectosOndeck([]);
      }
      if (data.foto_prospecto) {
        setFotoProspecto(data.foto_prospecto);
      } else {
        setFotoProspecto('');
      }
    } catch (err: any) {
      setOndeckError('Error al consultar la API: ' + (err.message || err));
    }
  };

  // Consultar ondeck al montar el componente y luego cada 5 segundos
  useEffect(() => {
    fetchOndeck();
    const interval = setInterval(fetchOndeck, 5000);
    return () => clearInterval(interval);
  }, []);

  // Detector de inactividad para screensaver
  const resetInactivityTimer = React.useCallback(() => {
    setScreensaverActive(false);
    
    if (inactivityTimer.current) {
      clearTimeout(inactivityTimer.current);
    }
    
    inactivityTimer.current = setTimeout(() => {
      setScreensaverActive(true);
      console.log('Screensaver activado');
    }, 30000); // 30 segundos
  }, []);

  // Iniciar timer de inactividad solo una vez al montar
  useEffect(() => {
    resetInactivityTimer();
    return () => {
      if (inactivityTimer.current) {
        clearTimeout(inactivityTimer.current);
      }
    };
  }, []); // Sin dependencias para que solo se ejecute al montar
  const [selectedUsuario, setSelectedUsuario] = useState<Usuario | null>(null);
  const [selectedClases, setSelectedClases] = useState<string[]>([]);

  // Selección automática de la primera clase disponible al abrir el popup
  React.useEffect(() => {
    if (popupVisible && selectedUsuario) {
      let opciones: string[] = [];
      // Clase actual con ambos filtros
      if (claseActualObj && claseActualObj.disciplina && claseActualObj.disciplina.trim().toLowerCase() !== 'ninguno') {
        const esCoach = selectedUsuario?.roles && selectedUsuario.roles.toLowerCase().includes('coach');
        let grupoEdad = '';
        let accesoTotal = false;
        if (
          selectedUsuario?.edad === undefined ||
          selectedUsuario?.edad === null ||
          (typeof selectedUsuario?.edad === 'number' && selectedUsuario.edad === 0) ||
          (typeof selectedUsuario?.edad === 'string' && String(selectedUsuario.edad).trim() === '')
        ) {
          accesoTotal = true;
        } else {
          if (selectedUsuario.edad >= 18) grupoEdad = 'adultos';
          else if (selectedUsuario.edad >= 11) grupoEdad = 'teens';
          else grupoEdad = 'kids';
        }
        // Filtro de grupo de edad
        let claseParaGrupo = true;
        if (!accesoTotal && !esCoach && claseActualObj.grupo) {
          const grupoClase = claseActualObj.grupo.trim().toLowerCase();
          if (grupoEdad === 'adultos' && !grupoClase.includes('adult')) claseParaGrupo = false;
          if (grupoEdad === 'teens' && !grupoClase.includes('teen')) claseParaGrupo = false;
          if (grupoEdad === 'kids' && !grupoClase.includes('kid')) claseParaGrupo = false;
        }
        // Filtro de acceso por usuario
        let accesoUsuario = false;
        if (esCoach || accesoTotal) {
          accesoUsuario = true;
        } else {
          const esTiquetera = selectedUsuario?.plan?.toLowerCase().includes('tiquetera');
          if (esTiquetera) {
            const saldoClases = selectedUsuario?.saldo_clases && selectedUsuario?.saldo_clases !== '0';
            accesoUsuario = !!saldoClases;
          } else {
            const clasesUsuario = Array.isArray(selectedUsuario?.clases_usuario) ? selectedUsuario?.clases_usuario.flat() : [];
            const disciplina = claseActualObj.disciplina ? claseActualObj.disciplina.trim() : '';
            accesoUsuario = clasesUsuario.some(c => c.trim().toLowerCase() === disciplina.toLowerCase());
          }
        }
        // Solo habilitado si ambos filtros son true (coaches tienen acceso total)
        const disponible = claseParaGrupo && accesoUsuario;
        if (disponible) {
          opciones.push('actual');
        }
      }
      // Próximas clases con filtro por grupo de edad
      if (clasesSiguientesArr.length > 0) {
        for (let idx = 0; idx < clasesSiguientesArr.length; idx++) {
          const clase = clasesSiguientesArr[idx];
          if (!clase.disciplina || clase.disciplina.trim().toLowerCase() === 'ninguno') continue;
          const esCoach = selectedUsuario?.roles && selectedUsuario.roles.toLowerCase().includes('coach');
          let disponible = false;
          // Filtrar por grupo de edad
          let grupoEdad = '';
          if (selectedUsuario?.edad !== undefined && selectedUsuario?.edad !== null) {
            if (selectedUsuario.edad >= 18) grupoEdad = 'adultos';
            else if (selectedUsuario.edad >= 11) grupoEdad = 'teens';
            else grupoEdad = 'kids';
          }
          // Determinar si la clase corresponde al grupo de edad (coaches tienen acceso total)
          let claseParaGrupo = true;
          if (!esCoach && clase.grupo) {
            const grupoClase = clase.grupo.trim().toLowerCase();
            if (grupoEdad === 'adultos' && !grupoClase.includes('adult')) claseParaGrupo = false;
            if (grupoEdad === 'teens' && !grupoClase.includes('teen')) claseParaGrupo = false;
            if (grupoEdad === 'kids' && !grupoClase.includes('kid')) claseParaGrupo = false;
          }
          if (!claseParaGrupo) continue;
          if (esCoach) {
            disponible = true;
          } else {
            const esTiquetera = selectedUsuario?.plan?.toLowerCase().includes('tiquetera');
            if (esTiquetera) {
              const saldoClases = selectedUsuario?.saldo_clases && selectedUsuario?.saldo_clases !== '0';
              disponible = !!saldoClases;
            } else {
              const clasesUsuario = Array.isArray(selectedUsuario?.clases_usuario) ? selectedUsuario?.clases_usuario.flat() : [];
              const disciplina = clase.disciplina ? clase.disciplina.trim() : '';
              disponible = clasesUsuario.some(c => c.trim().toLowerCase() === disciplina.toLowerCase());
            }
          }
          if (disponible) {
            opciones.push(idx.toString());
          }
        }
      }
      // Si no hay opciones, considerar personalizada
      if (opciones.length === 0 && !(selectedUsuario?.roles && selectedUsuario.roles.toLowerCase().includes('coach')) && selectedUsuario?.saldo_clases_personalizadas && selectedUsuario?.saldo_clases_personalizadas !== '0') {
        opciones.push('personalizada');
      }
      // Seleccionar solo la primera opción disponible
      if (opciones.length > 0) {
        setSelectedClases([opciones[0]]);
      } else {
        setSelectedClases([]);
      }
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [popupVisible, selectedUsuario]);
  const [clasesAhora, setClasesAhora] = useState<{ actual: string; proxima: string }>({ actual: '', proxima: '' });
  const [checkinMessage, setCheckinMessage] = useState<string>('');
  // Manejar la nueva estructura de clases_ahora
  const claseActualObj: {disciplina?: string, grupo?: string, start?: string, end?: string} | null =
    clasesAhoraRaw && clasesAhoraRaw.actual && typeof clasesAhoraRaw.actual === 'object' && !Array.isArray(clasesAhoraRaw.actual)
      ? clasesAhoraRaw.actual
      : null;
  const clasesSiguientesArr: Array<{disciplina?: string, grupo?: string, start?: string, end?: string}> =
    clasesAhoraRaw && Array.isArray(clasesAhoraRaw.proximas) ? clasesAhoraRaw.proximas : [];

  // Guardar clases disponibles por usuario si existen en la respuesta
  // (esto solo es ejemplo, puedes usarlo donde lo necesites en la app)
  // const clasesDisponiblesPorUsuario = usuarios.map(u => u.clases_disponibles || []);
  const [search, setSearch] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const onRefresh = () => {
    setRefreshing(true);
    fetchUsuarios();
    setTimeout(() => setRefreshing(false), 1000);
  };
  const [usuarios, setUsuarios] = useState<Usuario[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [fotos, setFotos] = useState<string[]>([]);
  const [logoUrl, setLogoUrl] = useState<string>('');
  const fetchUsuarios = () => {
    setLoading(true);
    setError('');
    fetch('https://www.satorijiujitsu.com.co/api/api.php?action=usuarios', {
      headers: {
        Authorization: 'Bearer ElArtesuave2023',
      },
    })
      .then(async (res) => {
        let data;
        try {
          data = await res.json();
        } catch (e) {
          setError('Respuesta inválida de la API');
          setLoading(false);
          return;
        }
        if (!res.ok) {
          setError(data.error ? `Error: ${data.error}` : `Error HTTP ${res.status}`);
          setLoading(false);
          return;
        }
        // Corregir asignación de clases_usuario por usuario
        if (data.usuarios && Array.isArray(data.usuarios)) {
          if (Array.isArray(data.clases_usuario)) {
            const usuariosConClases = data.usuarios.map((u: Usuario, idx: number) => ({
              ...u,
              clases_usuario: Array.isArray(data.clases_usuario[idx]) ? data.clases_usuario[idx] : [],
            }));
            setUsuarios(usuariosConClases);
          } else {
            setUsuarios(data.usuarios);
          }
        } else {
          setUsuarios([]);
        }
        if (data.fotos && Array.isArray(data.fotos)) {
          setFotos(data.fotos);
        } else {
          setFotos([]);
        }
        if (data.logo_url) {
          setLogoUrl(data.logo_url);
        } else {
          setLogoUrl('');
        }
        setClasesAhoraRaw(data.clases_ahora);
        if (data.clases_ahora && typeof data.clases_ahora === 'object') {
          let actual = '';
          let proxima = '';
          if (typeof data.clases_ahora.actual === 'string') {
            actual = data.clases_ahora.actual;
          }
          if (Array.isArray(data.clases_ahora.proximas) && data.clases_ahora.proximas.length > 0) {
            if (typeof data.clases_ahora.proximas[0] === 'string') {
              proxima = data.clases_ahora.proximas[0];
            } else if (data.clases_ahora.proximas[0] && typeof data.clases_ahora.proximas[0] === 'object' && data.clases_ahora.proximas[0].nombre) {
              proxima = data.clases_ahora.proximas[0].nombre;
            }
          } else if (data.clases_ahora.proximas && typeof data.clases_ahora.proximas === 'object' && data.clases_ahora.proximas.nombre) {
            proxima = data.clases_ahora.proximas.nombre;
          }
          setClasesAhora({ actual, proxima });
        } else {
          setClasesAhora({ actual: '', proxima: '' });
        }
        setLoading(false);
      })
      .catch((err) => {
        setError('Error al consultar la API: ' + err.message);
        setLoading(false);
      });
  };

  useEffect(() => {
    fetchUsuarios();
    
    // Verificar cada 30 segundos si la próxima clase ya empezó
    const interval = setInterval(() => {
      if (clasesSiguientesArr.length > 0 && clasesSiguientesArr[0].start) {
        const now = new Date();
        const currentTime = `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
        const nextClassStart = clasesSiguientesArr[0].start;
        
        // Comparar las horas en formato HH:MM
        if (currentTime >= nextClassStart) {
          console.log(`Hora actual (${currentTime}) >= hora de inicio de próxima clase (${nextClassStart}), actualizando usuarios...`);
          fetchUsuarios();
        }
      }
    }, 30000);
    
    return () => clearInterval(interval);
  }, []);

  const onRetry = () => {
    fetchUsuarios();
  };

  if (loading) return <Text style={{margin: 20, color: '#fff'}}>Cargando...</Text>;
  if (error) return (
    <View style={{margin: 20}}>
      <Text style={{color: 'red', marginBottom: 10}}>{error}</Text>
      <Button title="Reintentar" color="#2196F3" onPress={onRetry} />
    </View>
  );

  // Función para normalizar texto eliminando acentos
  const normalizeText = (text: string): string => {
    return text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  };

  // Filtrado de usuarios por búsqueda parcial ignorando acentos
  const filteredUsuarios = usuarios
    .map((u, i) => ({ ...u, _fotoIndex: i }))
    .filter(u => {
      if (!search.trim()) return true;
      const searchWords = normalizeText(search.trim()).split(/\s+/);
      const nombre = normalizeText(u.nombre);
      return searchWords.every(word => nombre.includes(word));
    });

  return (
    <View 
      style={{flex: 1}}
      onTouchStart={resetInactivityTimer}
      onTouchMove={resetInactivityTimer}
    >
      {/* Screensaver */}
      {screensaverActive && logoUrl && (
        <ScreensaverComponent logoUrl={logoUrl} onTouch={resetInactivityTimer} />
      )}
      {/* Overlay pop-up global */}
      {popupVisible && selectedUsuario && (
        <View
          style={[styles.popupOverlay, {position: 'absolute', left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%', zIndex: 9999}]}
        >
          {/* Fondo: cerrar modal al tocar fuera de la caja */}
          <TouchableOpacity
            style={{position: 'absolute', left: 0, top: 0, right: 0, bottom: 0, width: '100%', height: '100%'}}
            activeOpacity={1}
            onPress={() => {
              setPopupVisible(false);
              setSelectedUsuario(null);
              setSelectedClases([]);
              setShowPendingWarning(false);
              setShowInactiveWarning(false);
              // No borrar la casilla de búsqueda
              setCheckinMessage('');
            }}
          />
          {showPendingWarning && selectedUsuario.estado === 'pendiente' ? (
            // Mensaje de advertencia para estado pendiente
            <View style={styles.pendingWarningContainer}>
              <Text style={styles.pendingWarningText}>
                El estado actual de tu afiliación es <Text style={{fontWeight: 'bold'}}>PENDIENTE</Text>, acércate al FrontDesk para renovar o actualizar tu membresía
              </Text>
              <TouchableOpacity
                style={styles.pendingWarningButton}
                onPress={() => setShowPendingWarning(false)}
              >
                <Text style={styles.pendingWarningButtonText}>ACEPTAR</Text>
              </TouchableOpacity>
            </View>
          ) : showInactiveWarning && selectedUsuario.estado === 'inactivo' ? (
            // Mensaje de advertencia para estado inactivo
            <View style={styles.pendingWarningContainer}>
              <Text style={styles.pendingWarningText}>
                El estado actual de tu afiliación es <Text style={{fontWeight: 'bold'}}>INACTIVO</Text>, no es posible registrar tu ingreso, acércate al FRONTDESK para activar tu membresía
              </Text>
              <TouchableOpacity
                style={styles.pendingWarningButton}
                onPress={() => {
                  setShowInactiveWarning(false);
                  setPopupVisible(false);
                  setSelectedUsuario(null);
                  setSelectedClases([]);
                  setSearch('');
                  setCheckinMessage('');
                }}
              >
                <Text style={styles.pendingWarningButtonText}>ACEPTAR</Text>
              </TouchableOpacity>
            </View>
          ) : (
          <View style={styles.checkInAlumnoActual}>
            {checkinMessage ? (
              <Text style={{color: checkinMessage.includes('correctamente') ? 'green' : 'red', marginBottom: 8, fontWeight: 'bold'}}>{checkinMessage}</Text>
            ) : null}
            {(() => {
              let fotoUrl = null;
              if (selectedUsuario && typeof selectedUsuario._fotoIndex === 'number') {
                fotoUrl = fotos[selectedUsuario._fotoIndex] && fotos[selectedUsuario._fotoIndex].trim() !== '' ? fotos[selectedUsuario._fotoIndex] : null;
              } else if (selectedUsuario && selectedUsuario.foto && selectedUsuario.foto.trim() !== '') {
                fotoUrl = selectedUsuario.foto;
              }
              return fotoUrl ? (
                <Image
                  source={{ uri: fotoUrl }}
                  style={styles.popupAvatar}
                  resizeMode="cover"
                />
              ) : (
                <View style={[styles.popupAvatar, {justifyContent: 'center', alignItems: 'center'}]}>
                  <Text style={{color: '#222', fontSize: 32}}>?</Text>
                </View>
              );
            })()}
            <Text style={styles.popupNombre}>
              {selectedUsuario.nombre}
              {((selectedUsuario?.saldo_clases && selectedUsuario?.saldo_clases !== '0') || (selectedUsuario?.saldo_clases_personalizadas && selectedUsuario?.saldo_clases_personalizadas !== '0')) && (
                <>
                  {selectedUsuario?.saldo_clases && selectedUsuario?.saldo_clases !== '0' ? ` / T [${selectedUsuario?.saldo_clases}]` : ''}
                  {selectedUsuario?.saldo_clases_personalizadas && selectedUsuario?.saldo_clases_personalizadas !== '0' ? ` / P [${selectedUsuario?.saldo_clases_personalizadas}]` : ''}
                </>
              )}
            </Text>
            <Text style={styles.popupEstado}>Estado: {selectedUsuario.estado}</Text>
            {/* Opciones de selección de clases */}
            <View style={styles.popupClasesList}>
              {/* Opción Personalizada solo si no es coach */}
              {!(selectedUsuario?.roles && selectedUsuario.roles.toLowerCase().includes('coach')) && (
                <TouchableOpacity
                  style={[styles.popupClaseItem, (!selectedUsuario?.saldo_clases_personalizadas || selectedUsuario?.saldo_clases_personalizadas === '0') && {opacity: 0.5}]}
                  disabled={!selectedUsuario?.saldo_clases_personalizadas || selectedUsuario?.saldo_clases_personalizadas === '0'}
                  onPress={() => {
                    setSelectedClases((prev) => {
                      const key = 'personalizada';
                      if (prev.includes(key)) {
                        return prev.filter(c => c !== key);
                      } else {
                        return [...prev, key];
                      }
                    });
                  }}
                >
                  <View style={[styles.popupCheckbox, selectedClases.includes('personalizada') && styles.popupCheckboxSelected]} />
                  <Text style={styles.popupClaseText}>Personalizada</Text>
                </TouchableOpacity>
              )}

              {/* Clase actual con ambos filtros */}
              {claseActualObj && (claseActualObj.disciplina || claseActualObj.grupo) &&
                claseActualObj.disciplina && claseActualObj.disciplina.trim().toLowerCase() !== 'ninguno' ? (() => {
                const esCoach = selectedUsuario?.roles && selectedUsuario.roles.toLowerCase().includes('coach');
                let grupoEdad = '';
                let accesoTotal = false;
                if (
                  selectedUsuario?.edad === undefined ||
                  selectedUsuario?.edad === null ||
                  (typeof selectedUsuario?.edad === 'number' && selectedUsuario.edad === 0) ||
                  (typeof selectedUsuario?.edad === 'string' && String(selectedUsuario.edad).trim() === '')
                ) {
                  accesoTotal = true;
                } else {
                  if (selectedUsuario.edad >= 18) grupoEdad = 'adultos';
                  else if (selectedUsuario.edad >= 11) grupoEdad = 'teens';
                  else grupoEdad = 'kids';
                }
                // Filtro de grupo de edad
                let claseParaGrupo = true;
                if (!accesoTotal && !esCoach && claseActualObj.grupo) {
                  const grupoClase = claseActualObj.grupo.trim().toLowerCase();
                  if (grupoEdad === 'adultos' && !grupoClase.includes('adult')) claseParaGrupo = false;
                  if (grupoEdad === 'teens' && !grupoClase.includes('teen')) claseParaGrupo = false;
                  if (grupoEdad === 'kids' && !grupoClase.includes('kid')) claseParaGrupo = false;
                }
                // Filtro de acceso por usuario
                let accesoUsuario = false;
                if (esCoach || accesoTotal) {
                  accesoUsuario = true;
                } else {
                  const esTiquetera = selectedUsuario?.plan?.toLowerCase().includes('tiquetera');
                  if (esTiquetera) {
                    const saldoClases = selectedUsuario?.saldo_clases && selectedUsuario?.saldo_clases !== '0';
                    accesoUsuario = !!saldoClases;
                  } else {
                    const clasesUsuario = Array.isArray(selectedUsuario?.clases_usuario) ? selectedUsuario?.clases_usuario.flat() : [];
                    const disciplina = claseActualObj.disciplina ? claseActualObj.disciplina.trim() : '';
                    accesoUsuario = clasesUsuario.some(c => c.trim().toLowerCase() === disciplina.toLowerCase());
                  }
                }
                const disponible = claseParaGrupo && accesoUsuario;
                return (
                  <TouchableOpacity
                    style={[styles.popupClaseItem, !disponible && {opacity: 0.5}]}
                    disabled={!disponible}
                    onPress={() => {
                      setSelectedClases((prev) => {
                        const key = 'actual';
                        if (prev.includes(key)) {
                          return prev.filter(c => c !== key);
                        } else {
                          return [...prev, key];
                        }
                      });
                    }}
                  >
                    <View style={[styles.popupCheckbox, selectedClases.includes('actual') && styles.popupCheckboxSelected]} />
                    <Text style={styles.popupClaseText}>
                      {`${claseActualObj.disciplina || ''} - ${claseActualObj.grupo || ''} - ${claseActualObj.start || ''}`}
                    </Text>
                  </TouchableOpacity>
                );
              })() : null}

              {/* Próximas clases: siempre mostrar todas, solo aplicar filtro de grupo de edad para el estilo habilitado/deshabilitado */}
              {clasesSiguientesArr.length > 0 &&
                clasesSiguientesArr
                  .filter(clase => clase.disciplina && clase.disciplina.trim().toLowerCase() !== 'ninguno')
                  .map((clase: any, idx: number) => {
                    const esCoach = selectedUsuario?.roles && selectedUsuario.roles.toLowerCase().includes('coach');
                    let grupoEdad = '';
                    let accesoTotal = false;
                    if (
                      selectedUsuario?.edad === undefined ||
                      selectedUsuario?.edad === null ||
                      (typeof selectedUsuario?.edad === 'number' && selectedUsuario.edad === 0) ||
                      (typeof selectedUsuario?.edad === 'string' && String(selectedUsuario.edad).trim() === '')
                    ) {
                      accesoTotal = true;
                    } else {
                      if (selectedUsuario.edad >= 18) grupoEdad = 'adultos';
                      else if (selectedUsuario.edad >= 11) grupoEdad = 'teens';
                      else if (selectedUsuario.edad > 3) grupoEdad = 'kids';
                    }
                    // Filtro de grupo de edad
                    let claseParaGrupo = true;
                    if (!accesoTotal && !esCoach && clase.grupo) {
                      const grupoClase = clase.grupo.trim().toLowerCase();
                      if (grupoEdad === 'adultos' && !grupoClase.includes('adult')) claseParaGrupo = false;
                      if (grupoEdad === 'teens' && !grupoClase.includes('teen')) claseParaGrupo = false;
                      if (grupoEdad === 'kids' && !grupoClase.includes('kid')) claseParaGrupo = false;
                    }
                    // Filtro de acceso por usuario
                    let accesoUsuario = false;
                    if (esCoach || accesoTotal) {
                      accesoUsuario = true;
                    } else {
                      const esTiquetera = selectedUsuario?.plan?.toLowerCase().includes('tiquetera');
                      if (esTiquetera) {
                        const saldoClases = selectedUsuario?.saldo_clases && selectedUsuario?.saldo_clases !== '0';
                        accesoUsuario = !!saldoClases;
                      } else {
                        const clasesUsuario = Array.isArray(selectedUsuario?.clases_usuario) ? selectedUsuario?.clases_usuario.flat() : [];
                        const disciplina = clase.disciplina ? clase.disciplina.trim() : '';
                        accesoUsuario = clasesUsuario.some(c => c.trim().toLowerCase() === disciplina.toLowerCase());
                      }
                    }
                    // Solo habilitado si ambos filtros son true (coaches tienen acceso total)
                    const disponible = claseParaGrupo && accesoUsuario;
                    return (
                      <TouchableOpacity
                        key={idx}
                        style={[styles.popupClaseItem, !disponible && {opacity: 0.5}]}
                        disabled={!disponible}
                        onPress={() => {
                          setSelectedClases((prev) => {
                            if (prev.includes(idx.toString())) {
                              return prev.filter(c => c !== idx.toString());
                            } else {
                              return [...prev, idx.toString()];
                            }
                          });
                        }}
                      >
                        <View style={[styles.popupCheckbox, selectedClases.includes(idx.toString()) && styles.popupCheckboxSelected]} />
                        <Text style={styles.popupClaseText}>
                          {`${clase.disciplina || ''} - ${clase.grupo || ''} - ${clase.start || ''}`}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
            </View>
            <View style={styles.popupButtons}>
              <Button
                title="Cancelar"
                color="#888"
                onPress={() => {
                  setPopupVisible(false);
                  setSelectedUsuario(null);
                  setSelectedClases([]);
                  setShowPendingWarning(false);
                  setShowInactiveWarning(false);
                  setSearch('');
                  setCheckinMessage('');
                }}
              />
              <Button
                title="Aceptar"
                color="#2196F3"
                onPress={async () => {
                  if (!selectedUsuario || selectedClases.length === 0) {
                    setCheckinMessage('Debes seleccionar al menos una clase.');
                    return;
                  }
                  // Construir el array de clases seleccionadas con información completa
                  let clasesSeleccionadas: any[] = [];
                  // Personalizada
                  if (selectedClases.includes('personalizada')) {
                    const now = new Date();
                    const hora = now.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false });
                    clasesSeleccionadas.push({ 
                      tipo: 'actual', 
                      disciplina: 'Personalizada', 
                      grupo: '', 
                      start: hora, 
                      end: '' 
                    });
                  }
                  // Clase actual
                  if (selectedClases.includes('actual') && claseActualObj) {
                    clasesSeleccionadas.push({
                      tipo: 'actual',
                      disciplina: claseActualObj.disciplina || '',
                      grupo: claseActualObj.grupo || '',
                      start: claseActualObj.start || '',
                      end: claseActualObj.end || ''
                    });
                  }
                  // Próximas clases
                  selectedClases.forEach((c) => {
                    if (!isNaN(Number(c))) {
                      const idx = Number(c);
                      if (clasesSiguientesArr[idx]) {
                        const clase = clasesSiguientesArr[idx];
                        clasesSeleccionadas.push({
                          tipo: 'proxima',
                          disciplina: clase.disciplina || '',
                          grupo: clase.grupo || '',
                          start: clase.start || '',
                          end: clase.end || ''
                        });
                      }
                    }
                  });
                  try {
                    const response = await fetch('https://www.satorijiujitsu.com.co/api/api.php?action=check-in', {
                      method: 'POST',
                      headers: {
                        'Content-Type': 'application/json',
                        Authorization: 'Bearer ElArtesuave2023',
                      },
                      body: JSON.stringify({
                        nombre_tabla: selectedUsuario.nombre_tabla,
                        clases: clasesSeleccionadas,
                      }),
                    });
                    
                    const responseText = await response.text();
                    console.log('Respuesta de la API (texto):', responseText);
                    
                    let result;
                    try {
                      result = JSON.parse(responseText);
                    } catch (parseError: any) {
                      console.error('Error al parsear JSON:', parseError);
                      console.error('Respuesta completa de la API:', responseText);
                      setCheckinMessage(`Error: La API no devolvió un JSON válido. Ver consola para detalles.`);
                      return;
                    }
                    
                    if (response.ok && result.success) {
                      // Verificar si hay un mensaje de duplicado
                      if (result.message && result.message.toLowerCase().includes('duplicado')) {
                        setCheckinMessage('El usuario ya ha realizado Check-In para esta clase');
                      } else {
                        setCheckinMessage('Check-in realizado correctamente.');
                      }
                      setTimeout(() => {
                        setPopupVisible(false);
                        setSelectedUsuario(null);
                        setSelectedClases([]);
                        setShowPendingWarning(false);
                        setShowInactiveWarning(false);
                        setSearch('');
                        setCheckinMessage('');
                      }, 1000);
                    } else {
                      setCheckinMessage(result.error ? result.error : 'Error al realizar el check-in.');
                    }
                  } catch (err: any) {
                    const errorMsg = err.message || String(err);
                    setCheckinMessage(`Error de red al realizar el check-in: ${errorMsg}`);
                    console.error('Error completo en check-in:', err);
                  }
                }}
              />
            </View>
          </View>
          )}
        </View>
      )}
      {/* Layout principal */}
      <View style={{flex: 1, flexDirection: 'row', backgroundColor: '#222', paddingBottom: 32}}>
        {/* Menú lateral */}
        <View style={styles.menuLateral}>
          {logoUrl ? (
            <View style={styles.logoContainer}>
              <Image source={{ uri: logoUrl }} style={styles.logoImg} resizeMode="contain" />
            </View>
          ) : null}
          <TextInput
            style={styles.searchInput}
            placeholder="Buscar nombre..."
            placeholderTextColor="#888"
            value={search}
            onChangeText={setSearch}
          />
          <FlatList
            data={filteredUsuarios}
            keyExtractor={(item) => item.id.toString()}
            keyboardShouldPersistTaps='handled'
            renderItem={({ item }) => {
              const fotoUrl = fotos[item._fotoIndex] && fotos[item._fotoIndex].trim() !== '' ? fotos[item._fotoIndex] : null;
              let borderColor = '#444';
              if (item.estado === 'activo') borderColor = 'green';
              else if (item.estado === 'saldobajo') borderColor = 'yellow';
              else if (item.estado === 'pendiente') borderColor = 'orange';
              else if (item.estado === 'inactivo') borderColor = 'red';
              else if (item.estado === 'congelado') borderColor = '#4FC3F7';
              return (
                <TouchableOpacity
                  activeOpacity={0.7}
                  onPress={() => {
                    Keyboard.dismiss();
                    setSelectedUsuario(item);
                    setPopupVisible(true);
                    if (item.estado === 'pendiente') {
                      setShowPendingWarning(true);
                      setShowInactiveWarning(false);
                    } else if (item.estado === 'inactivo') {
                      setShowInactiveWarning(true);
                      setShowPendingWarning(false);
                    } else {
                      setShowPendingWarning(false);
                      setShowInactiveWarning(false);
                    }
                  }}
                >
                  <View style={[styles.menuItem, { borderColor }] }>
                    {fotoUrl ? (
                      <Image
                        source={{ uri: fotoUrl }}
                        style={styles.avatar}
                        resizeMode="cover"
                      />
                    ) : (
                      <View style={[styles.avatar, {justifyContent: 'center', alignItems: 'center'}]}>
                        <Text style={{color: '#fff', fontSize: 18}}>?</Text>
                      </View>
                    )}
                    <Text style={styles.menuItemText}>{item.nombre}</Text>
                  </View>
                </TouchableOpacity>
              );
            }}
            refreshControl={
              <RefreshControl
                refreshing={refreshing}
                onRefresh={onRefresh}
                colors={["#2196F3"]}
                progressBackgroundColor="#222"
              />
            }
          />
        </View>
        <View style={{flex: 1}}>
          {/* Menú superior */}
          <View style={styles.menuSuperior}>
            <View style={styles.menuSuperiorContent}>
              <Text style={styles.menuSuperiorText}>
                <Text style={{color: '#fff'}}>CLASE ACTUAL: </Text>
                <Text style={{color: 'red'}}>
                  {claseActualObj && (claseActualObj.disciplina || claseActualObj.grupo)
                    ? `${claseActualObj.disciplina || ''} - ${claseActualObj.grupo || ''}`
                    : 'ninguno'}
                </Text>
              </Text>
              <Text style={styles.menuSuperiorText}>
                <Text style={{color: '#fff'}}>PRÓXIMA CLASE: </Text>
                <Text style={{color: 'red'}}>
                  {clasesSiguientesArr.length > 0 && (clasesSiguientesArr[0].disciplina || clasesSiguientesArr[0].grupo)
                    ? `${clasesSiguientesArr[0].disciplina || ''} - ${clasesSiguientesArr[0].grupo || ''}`
                    : 'ninguno'}
                </Text>
              </Text>
            </View>
          </View>
          {/* Área principal: resultado de la consulta ondeck */}
          <View style={styles.mainContent}>
            {ondeckError ? (
              <Text style={{color: 'red', fontSize: 16}}>{ondeckError}</Text>
            ) : (prospectosOndeck.length > 0 || (ondeckResult && ondeckResult.ondeck && Array.isArray(ondeckResult.ondeck) && ondeckResult.ondeck.length > 0)) ? (
              <View style={{padding: 20}}>
                {/* Conteo de alumnos ondeck */}
                <View style={{marginBottom: 20, alignItems: 'center'}}>
                  <Text style={{color: '#fff', fontSize: 18, fontWeight: 'bold'}}>
                    ALUMNOS ON DECK: <Text style={{color: '#2196F3'}}>{(ondeckResult && ondeckResult.ondeck && Array.isArray(ondeckResult.ondeck) ? ondeckResult.ondeck.length : 0) + prospectosOndeck.length}</Text>
                  </Text>
                </View>
                <View style={{flexDirection: 'row', flexWrap: 'wrap', gap: 20}}>
                {/* Prospectos primero con borde dorado */}
                {prospectosOndeck.map((nombreProspecto: string, idx: number) => (
                  <View key={`prospecto-${idx}`} style={{
                    alignItems: 'center',
                    width: 115,
                    backgroundColor: '#333',
                    borderRadius: 15,
                    borderWidth: 4,
                    borderColor: '#FFD700',
                    padding: 12,
                  }}>
                    <View style={{
                      width: 80,
                      height: 80,
                      borderRadius: 40,
                      overflow: 'hidden',
                      backgroundColor: '#444',
                      justifyContent: 'center',
                      alignItems: 'center',
                      marginBottom: 8
                    }}>
                      {fotoProspecto ? (
                        <Image
                          source={{ uri: fotoProspecto }}
                          style={{width: '100%', height: '100%'}}
                          resizeMode="cover"
                        />
                      ) : (
                        <Text style={{color: '#fff', fontSize: 28}}>?</Text>
                      )}
                    </View>
                    <Text style={{color: '#FFD700', fontSize: 13, fontWeight: 'bold', textAlign: 'center'}}>{nombreProspecto}</Text>
                  </View>
                ))}
                {/* Usuarios normales después */}
                {ondeckResult && ondeckResult.ondeck && Array.isArray(ondeckResult.ondeck) && ondeckResult.ondeck.map((nombreTabla: string, idx: number) => {
                  const usuario = usuarios.find(u => u.nombre_tabla === nombreTabla);
                  if (!usuario) return null;
                  
                  const fotoUrl = fotos[usuarios.indexOf(usuario)] && fotos[usuarios.indexOf(usuario)].trim() !== '' ? fotos[usuarios.indexOf(usuario)] : null;
                  let borderColor = '#444';
                  if (usuario.estado === 'activo') borderColor = 'green';
                  else if (usuario.estado === 'saldobajo') borderColor = 'yellow';
                  else if (usuario.estado === 'pendiente') borderColor = 'orange';
                  else if (usuario.estado === 'inactivo') borderColor = 'red';
                  else if (usuario.estado === 'congelado') borderColor = '#4FC3F7';
                  
                  return (
                    <View key={`usuario-${idx}`} style={{alignItems: 'center', width: 120}}>
                      <View style={{
                        width: 100,
                        height: 100,
                        borderRadius: 50,
                        borderWidth: 3,
                        borderColor: borderColor,
                        overflow: 'hidden',
                        backgroundColor: '#444',
                        justifyContent: 'center',
                        alignItems: 'center'
                      }}>
                        {fotoUrl ? (
                          <Image
                            source={{ uri: fotoUrl }}
                            style={{width: '100%', height: '100%'}}
                            resizeMode="cover"
                          />
                        ) : (
                          <Text style={{color: '#fff', fontSize: 32}}>?</Text>
                        )}
                      </View>
                      <Text style={{color: '#fff', fontSize: 14, marginTop: 8, textAlign: 'center'}}>{usuario.nombre}</Text>
                    </View>
                  );
                })}
              </View>
              </View>
            ) : (
              <Text style={{color: '#888', fontSize: 16, padding: 20}}>Sin usuarios en espera</Text>
            )}
          </View>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  popupOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 9999,
  },
  checkInAlumnoActual: {
    width: 320,
    backgroundColor: '#eee',
    borderRadius: 5,
    padding: 20,
    alignItems: 'center',
  },
  popupAvatar: {
    width: 100,
    height: 100,
    borderRadius: 50,
    marginBottom: 12,
    backgroundColor: '#ccc',
  },
  popupNombre: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#222',
    textAlign: 'center',
    marginBottom: 8,
  },
  popupEstado: {
    fontSize: 16,
    color: '#444',
    marginBottom: 4,
    textAlign: 'center',
  },
  popupTabla: {
    fontSize: 16,
    color: '#444',
    marginBottom: 12,
    textAlign: 'center',
  },
  popupClasesTitulo: {
    fontSize: 16,
    color: '#222',
    fontWeight: 'bold',
    marginBottom: 6,
    textAlign: 'center',
  },
  popupClasesList: {
    width: '100%',
    marginBottom: 16,
  },
  popupClaseItem: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  popupCheckbox: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 2,
    borderColor: '#888',
    marginRight: 8,
    backgroundColor: '#fff',
  },
  popupCheckboxSelected: {
    backgroundColor: '#2196F3',
    borderColor: '#2196F3',
  },
  popupClaseText: {
    fontSize: 15,
    color: '#222',
  },
  popupButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    width: '100%',
    marginTop: 12,
    gap: 12,
  },
  menuSuperiorContent: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'column',
  },
  searchInput: {
    backgroundColor: '#333',
    color: '#fff',
    borderRadius: 5,
    marginHorizontal: 12,
    marginBottom: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    fontSize: 14,
    borderWidth: 1,
    borderColor: '#444',
  },
  menuLateral: {
    position: 'absolute',
    left: 0,
    top: 0,
    width: 300,
    height: '100%',
    backgroundColor: '#222',
    zIndex: 800,
    borderRightWidth: 1,
    borderRightColor: '#333',
    paddingTop: 0,
    paddingBottom: 0,
    overflow: 'scroll',
  },
  logoContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 16,
    width: '100%',
  },
  logoImg: {
    width: 120,
    height: 80,
    marginBottom: 8,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#444',
    borderRadius: 5,
    margin: 8,
    padding: 8,
    backgroundColor: '#222',
  },
  menuItemText: {
    color: '#fff',
    fontSize: 12,
    marginLeft: 10,
  },
  menuSuperior: {
    position: 'absolute',
    left: 300,
    top: 0,
    right: 0,
    height: 100,
    backgroundColor: '#333',
    justifyContent: 'center',
    paddingLeft: 16,
    zIndex: 700,
    borderBottomWidth: 1,
    borderBottomColor: '#444',
  },
  menuSuperiorText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: 'bold',
  },
  mainContent: {
    marginTop: 100,
    marginLeft: 300, // ancho del menú lateral
    flex: 1,
    backgroundColor: '#222',
  },
  urlText: {
    color: '#aaa',
    fontSize: 12,
    marginTop: 2,
    marginBottom: 2,
  },
  container: {
    flex: 1,
    backgroundColor: '#222',
  },
  userRow: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    borderBottomWidth: 1,
    borderColor: '#333',
  },
  avatarContainer: {
    marginRight: 16,
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: '#444',
  },
  userName: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '500',
  },
  pendingWarningContainer: {
    width: 320,
    backgroundColor: '#d32f2f',
    borderRadius: 5,
    padding: 24,
    alignItems: 'center',
  },
  pendingWarningText: {
    color: '#fff',
    fontSize: 16,
    textAlign: 'center',
    marginBottom: 20,
    lineHeight: 24,
  },
  pendingWarningButton: {
    backgroundColor: '#fff',
    paddingVertical: 12,
    paddingHorizontal: 40,
    borderRadius: 5,
  },
  pendingWarningButtonText: {
    color: '#d32f2f',
    fontSize: 16,
    fontWeight: 'bold',
  },
});

// Componente de screensaver con logo flotante
function ScreensaverComponent({ logoUrl, onTouch }: { logoUrl: string; onTouch: () => void }) {
  const { width, height } = Dimensions.get('window');
  const logoWidth = 240;
  const logoHeight = 160;
  const position = useRef(new Animated.ValueXY({ x: Math.random() * (width - logoWidth), y: Math.random() * (height - logoHeight) })).current;
  const velocity = useRef({ x: (Math.random() - 0.5) * 6, y: (Math.random() - 0.5) * 6 });

  useEffect(() => {
    const currentPos = { x: Math.random() * (width - logoWidth), y: Math.random() * (height - logoHeight) };
    const animate = () => {
      const interval = setInterval(() => {
        currentPos.x += velocity.current.x;
        currentPos.y += velocity.current.y;

        // Rebotar en los bordes
        if (currentPos.x <= 0 || currentPos.x >= width - logoWidth) {
          velocity.current.x *= -1;
          currentPos.x = Math.max(0, Math.min(width - logoWidth, currentPos.x));
        }
        if (currentPos.y <= 0 || currentPos.y >= height - logoHeight) {
          velocity.current.y *= -1;
          currentPos.y = Math.max(0, Math.min(height - logoHeight, currentPos.y));
        }

        position.setValue({ x: currentPos.x, y: currentPos.y });
      }, 16);

      return () => clearInterval(interval);
    };

    const cleanup = animate();
    return cleanup;
  }, [position, width, height, logoWidth, logoHeight]);

  return (
    <TouchableOpacity 
      style={{
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0,
        width: '100%',
        height: '100%',
        backgroundColor: '#000',
        zIndex: 10000,
      }}
      activeOpacity={1}
      onPress={onTouch}
    >
      <Animated.View
        style={{
          position: 'absolute',
          left: position.x,
          top: position.y,
          width: logoWidth,
          height: logoHeight,
        }}
      >
        <Image 
          source={{ uri: logoUrl }} 
          style={{ width: logoWidth, height: logoHeight }} 
          resizeMode="contain"
        />
      </Animated.View>
    </TouchableOpacity>
  );
}

export default App;

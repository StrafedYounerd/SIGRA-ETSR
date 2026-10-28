// ============================================================
// SIGRA-ETSR · Componente B
// js/layout-plano.js — Layout del plano de sala (función pura)
// ============================================================
// Fuente ÚNICA del cálculo de coordenadas del plano. La cargan:
//   - public/docente/mapa-sala.php  (antes de js/mapa.js)
//   - public/admin/plano-editor.php  (el editor de admin)
// No tiene dependencias ni efectos: solo define constantes y una
// función pura. Así el editor y el plano del docentearden SIEMPRE
// las mismas coordenadas para la misma sala.
//
// Orientación REAL de cada fila (la posición 1 es la primera que ve
// el docente al entrar por la puerta, abajo a la derecha):
//   fila 1 (pata derecha):   posición 1 ABAJO, sube hacia el fondo
//   fila 2 (fila del fondo): posición 1 a la DERECHA
//   fila 3 (pata izquierda): posición 1 ARRIBA, baja hacia la puerta

// Sentido de numeración de cada fila.
const ORIENTACION = { 1: "asc", 2: "der", 3: "desc" };

// Tope técnico de posiciones por fila (único cap del plano). El editor
// de admin no puede agregar más lugares que este número por fila.
// Máximo 24 equipos por sala (8 por fila × 3 filas).
const MAX_POR_FILA = 8;   // debe coincidir con $max_por_fila de
                           // public/admin/plano-guardar.php (y con el
                           // tope que valida plano-editor.php).

// Devuelve el índice de "ranura" que ocupa la posición i+1 de una fila
// (las ranuras se cuentan de arriba hacia abajo, o de izquierda a derecha).
//   "asc":  la posición 1 arranca ABAJO y la lista sube   (eje vertical)
//   "desc": la posición 1 arranca ARRIBA y la lista baja  (eje vertical)
//   "der":  la posición 1 arranca a la DERECHA y va a la izquierda
//   "izq":  la posición 1 arranca a la IZQUIERDA y va a la derecha
function indiceEnFila(orient, i, total) {
    const invertida = (orient === "asc" || orient === "der");
    return invertida ? total - 1 - i : i;
}

// Calcula el layout completo del plano a partir de los totales por fila
// {1: n, 2: n, 3: n} (en realidad: la MAYOR posición usada de cada fila,
// así una posición saltada queda como hueco libre, igual que hoy).
// Es una función pura: recibe totales, devuelve coordenadas.
// Con los datos actuales (≤5 por columna, ≤3 al fondo) el conjunto de
// coordenadas es IDÉNTICO al histórico — solo cambia el sentido de
// numeración de las filas 1 y 2. Más equipos que eso hacen crecer el
// viewBox: más alto para las columnas, más ancho para la fila del fondo,
// sin solapes ni cortes.
function calcularLayout(totalesPorFila) {
    const n = [1, 2, 3].map(f => Math.min(MAX_POR_FILA, totalesPorFila[f] || 0));
    const X_IZQ = 36, X_DER = 740;          // columnas pegadas a las paredes
    const Y_COL = 130, SEP_Y = 78;          // primera mesa y separación vertical
    const Y_TOP = 36;                        // fila del fondo
    const MESA_LADO_W = 124, MESA_LADO_H = 66;
    const MESA_TOP_W = 108, MESA_TOP_H = 70;
    const SEP_H = 122;      // separación histórica de la fila del fondo
    const SEP_H_MIN = 112;  // menos que esto: el plano se ensancha
    const FILA2_BASE = 274; // anclaje histórico de la fila 2 (n ≤ 3)

    // Columnas: el alto del plano crece con la fila más larga, dejando
    // 32px de aire entre la última mesa y el frente (pared del medio).
    const nLargo = Math.max(n[0], n[2]);
    const fondoCol = nLargo ? Y_COL + (nLargo - 1) * SEP_Y + MESA_LADO_H : 0;
    const dy = Math.max(0, fondoCol + 32 - 540);

    // Fila del fondo: hasta 3 usa el anclaje histórico (274 + i·122),
    // que mantiene el conjunto de coordenadas de las salas actuales.
    // Desde 4 se centra en el hueco de la U; si no entra con la
    // separación mínima, la pared derecha se corre (W crece).
    let W = 900, sep2 = SEP_H, x0 = FILA2_BASE;
    if (n[1] >= 4) {
        const huecoBase = X_DER - (X_IZQ + MESA_LADO_W);   // interior de la U
        sep2 = Math.min(SEP_H, (huecoBase - MESA_TOP_W) / (n[1] - 1));
        if (sep2 < SEP_H_MIN) {
            sep2 = SEP_H_MIN;
            W += Math.ceil(MESA_TOP_W + (n[1] - 1) * sep2 + 80 - huecoBase);
        }
    }
    const xDer = X_DER + (W - 900);   // pared derecha corrida, si creció
    if (n[1] >= 4) {
        const hueco = xDer - (X_IZQ + MESA_LADO_W);
        const bloque = MESA_TOP_W + (n[1] - 1) * sep2;
        x0 = (X_IZQ + MESA_LADO_W + xDer - bloque) / 2;
    }

    // Coordenadas por posición (índice 0 = posición 1). El sentido de
    // cada fila lo fija ORIENTACION:
    //   fila 1 "asc":  pos 1 abajo, la lista sube
    //   fila 2 "der":  pos 1 a la derecha, la lista va hacia la izquierda
    //   fila 3 "desc": pos 1 arriba, la lista baja
    const posiciones = {};
    [1, 2, 3].forEach(f => {
        const total = n[f - 1];
        const orient = ORIENTACION[f];
        posiciones[f] = Array.from({ length: total }, (_, i) => {
            const k = indiceEnFila(orient, i, total);
            if (f === 2) return { x: x0 + k * sep2, y: Y_TOP, o: "top" };
            if (f === 3) return { x: X_IZQ, y: Y_COL + k * SEP_Y, o: "izq" };
            return { x: xDer, y: Y_COL + k * SEP_Y, o: "der" };
        });
    });
    return { W, H: 620 + dy, xDer, dy, posiciones };
}

// Tamaño de la mesa según la orientación (columna lateral o fila del fondo).
function tamanoMesa(o) {
    return o === "top" ? { w: 108, h: 70 } : { w: 124, h: 66 };
}

// dashboard.js

const dashboardData = window.dashboardData || {};
const metricasVisuais = dashboardData.metricas_visuais || {};

function getVisualMetrica(
  chave,
  fallbackColor = "#60a5fa",
  fallbackBg = "rgba(96, 165, 250, 0.18)",
) {
  return {
    color: metricasVisuais[chave]?.cor || fallbackColor,
    background: metricasVisuais[chave]?.cor_fundo || fallbackBg,
    estado: metricasVisuais[chave]?.estado || "neutral",
    variacao: metricasVisuais[chave]?.variacao_percentual ?? null,
  };
}

document.addEventListener("DOMContentLoaded", function () {
  if (window.lucide) {
    lucide.createIcons();
  }

  var data = window.dashboardData || {};

  renderChartGastoResultado(data.serie_gasto_resultado || null);
  renderChartCustoResultado(data.serie_custo_resultado || null);
  renderChartFreqCtr(data.serie_freq_ctr || null);

  setupPeriodoCustom();
  setupAutoFilters();
});

function normalizarNumero(valor) {
  if (valor === null || valor === undefined || valor === "") {
    return 0;
  }

  return parseFloat(String(valor).replace(",", ".")) || 0;
}

function classificarValorMetricaJS(config, valor) {
  valor = normalizarNumero(valor);

  const tipo = String(config?.tipo_leitura || "").trim();

  const criticoMin = normalizarNumero(config?.critico_min);
  const alertaMin = normalizarNumero(config?.alerta_min);
  const idealMin = normalizarNumero(config?.ideal_min);
  const idealMax = normalizarNumero(config?.ideal_max);
  const alertaMax = normalizarNumero(config?.alerta_max);
  const criticoMax = normalizarNumero(config?.critico_max);

  const temFaixaMin = criticoMin > 0 || alertaMin > 0 || idealMin > 0;
  const temFaixaMax = idealMax > 0 || alertaMax > 0 || criticoMax > 0;
  const temAlgumaFaixa = temFaixaMin || temFaixaMax;

  if (!tipo || !temAlgumaFaixa) {
    return "neutral";
  }

  if (tipo === "menor_melhor") {
    if (idealMax > 0 && valor <= idealMax) return "good";
    if (alertaMax > 0 && valor <= alertaMax) return "warning";
    if (criticoMax > 0 && valor >= criticoMax) return "bad";
    return "neutral";
  }

  if (tipo === "maior_melhor") {
    if (idealMin > 0 && valor >= idealMin) return "good";
    if (alertaMin > 0 && valor >= alertaMin) return "warning";
    if (criticoMin > 0 && valor <= criticoMin) return "bad";
    return "neutral";
  }

  if (tipo === "faixa_ideal") {
    if (
      idealMin > 0 &&
      idealMax > 0 &&
      valor >= idealMin &&
      valor <= idealMax
    ) {
      return "good";
    }

    const warningInferior =
      alertaMin > 0 && idealMin > 0 && valor >= alertaMin && valor < idealMin;
    const warningSuperior =
      idealMax > 0 && alertaMax > 0 && valor > idealMax && valor <= alertaMax;

    if (warningInferior || warningSuperior) {
      return "warning";
    }

    const badInferior = criticoMin > 0 && valor <= criticoMin;
    const badSuperior = criticoMax > 0 && valor >= criticoMax;

    if (badInferior || badSuperior) {
      return "bad";
    }

    return "neutral";
  }

  return "neutral";
}

function getColorsByEstado(estado) {
  switch (estado) {
    case "good":
      return {
        solid: "#22c55e",
        soft: "rgba(34, 197, 94, 0.25)",
      };
    case "warning":
      return {
        solid: "#f59e0b",
        soft: "rgba(245, 158, 11, 0.25)",
      };
    case "bad":
      return {
        solid: "#ef4444",
        soft: "rgba(239, 68, 68, 0.25)",
      };
    default:
      return {
        solid: "#60a5fa",
        soft: "rgba(96, 165, 250, 0.25)",
      };
  }
}

function setupAutoFilters() {
  var form = document.getElementById("filtersForm");
  if (!form) return;

  var contaSelect = document.getElementById("conta_id");
  var campanhaSelect = document.getElementById("campanha_id");
  var periodoRadios = document.querySelectorAll('input[name="periodo"]');
  var dataInicio = document.getElementById("data_inicio");
  var dataFim = document.getElementById("data_fim");

  function submitForm() {
    form.submit();
  }

  if (contaSelect) {
    contaSelect.addEventListener("change", function () {
      if (campanhaSelect) {
        campanhaSelect.value = "";
      }
      submitForm();
    });
  }

  if (campanhaSelect) {
    campanhaSelect.addEventListener("change", function () {
      submitForm();
    });
  }

  if (periodoRadios && periodoRadios.length) {
    for (var i = 0; i < periodoRadios.length; i++) {
      periodoRadios[i].addEventListener("change", function () {
        var selecionado = document.querySelector(
          'input[name="periodo"]:checked',
        );

        if (selecionado && selecionado.value !== "custom") {
          submitForm();
        }
      });
    }
  }

  if (dataInicio) {
    dataInicio.addEventListener("change", function () {
      var selecionado = document.querySelector('input[name="periodo"]:checked');
      if (
        selecionado &&
        selecionado.value === "custom" &&
        dataFim &&
        dataFim.value !== ""
      ) {
        submitForm();
      }
    });
  }

  if (dataFim) {
    dataFim.addEventListener("change", function () {
      var selecionado = document.querySelector('input[name="periodo"]:checked');
      if (
        selecionado &&
        selecionado.value === "custom" &&
        dataInicio &&
        dataInicio.value !== ""
      ) {
        submitForm();
      }
    });
  }
}

function setupPeriodoCustom() {
  var radios = document.querySelectorAll('input[name="periodo"]');
  var dataInicio = document.getElementById("data_inicio");
  var dataFim = document.getElementById("data_fim");

  function toggleDates() {
    var selecionado = document.querySelector('input[name="periodo"]:checked');
    var isCustom = selecionado && selecionado.value === "custom";

    if (dataInicio) dataInicio.disabled = !isCustom;
    if (dataFim) dataFim.disabled = !isCustom;
  }

  for (var i = 0; i < radios.length; i++) {
    radios[i].addEventListener("change", toggleDates);
  }

  toggleDates();
}

function defaultChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: "index",
      intersect: false,
    },
    plugins: {
      legend: {
        labels: {
          color: "#cbd5e1",
        },
      },
    },
    scales: {
      x: {
        ticks: {
          color: "#94a3b8",
        },
        grid: {
          color: "rgba(148, 163, 184, 0.08)",
        },
      },
      y: {
        ticks: {
          color: "#94a3b8",
        },
        grid: {
          color: "rgba(148, 163, 184, 0.08)",
        },
      },
    },
  };
}

function renderChartGastoResultado(dataset) {
  var canvas = document.getElementById("chartGastoResultado");
  if (!canvas || !dataset) return;

  const visualGasto = getVisualMetrica(
    "gasto",
    "#60a5fa",
    "rgba(96, 165, 250, 0.15)",
  );

  const visualResultados = getVisualMetrica(
    "resultados",
    "#22c55e",
    "rgba(34, 197, 94, 0.10)",
  );

  new Chart(canvas, {
    type: "line",
    data: {
      labels: dataset.labels || [],
      datasets: [
        {
          label: "Gasto",
          data: dataset.gasto || [],
          borderColor: visualGasto.color,
          backgroundColor: visualGasto.background,
          pointBackgroundColor: visualGasto.color,
          pointBorderColor: visualGasto.color,
          yAxisID: "y",
          tension: 0.35,
          fill: true,
        },
        {
          label: "Resultados",
          data: dataset.resultados || [],
          borderColor: visualResultados.color,
          backgroundColor: visualResultados.background,
          pointBackgroundColor: visualResultados.color,
          pointBorderColor: visualResultados.color,
          yAxisID: "y1",
          tension: 0.35,
          fill: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            color: "#cbd5e1",
          },
        },
      },
      scales: {
        x: {
          ticks: { color: "#94a3b8" },
          grid: { color: "rgba(148, 163, 184, 0.08)" },
        },
        y: {
          position: "left",
          ticks: { color: "#94a3b8" },
          grid: { color: "rgba(148, 163, 184, 0.08)" },
        },
        y1: {
          position: "right",
          ticks: { color: "#94a3b8" },
          grid: { drawOnChartArea: false },
        },
      },
    },
  });
}

function renderChartCustoResultado(dataset) {
  var canvas = document.getElementById("chartCustoResultado");
  if (!canvas || !dataset) return;

  const configMetricas = dashboardData.config_metricas || {};
  const configCustoResultado =
    configMetricas.custo_resultado ||
    configMetricas.custo_por_resultado ||
    {};

  const valores = dataset.custos || [];

  const borderColors = valores.map((valor) => {
    const estado = classificarValorMetricaJS(configCustoResultado, valor);
    return getColorsByEstado(estado).solid;
  });

  const backgroundColors = valores.map((valor) => {
    const estado = classificarValorMetricaJS(configCustoResultado, valor);
    return getColorsByEstado(estado).soft;
  });

  new Chart(canvas, {
    type: "bar",
    data: {
      labels: dataset.labels || [],
      datasets: [
        {
          label: "Custo por Resultado",
          data: valores,
          borderColor: borderColors,
          backgroundColor: backgroundColors,
          borderWidth: 1.5,
        },
      ],
    },
    options: defaultChartOptions(),
  });
}

function renderChartFreqCtr(dataset) {
  var canvas = document.getElementById("chartFreqCtr");
  if (!canvas || !dataset) return;

  const visualCTR = getVisualMetrica(
    "ctr",
    "#f59e0b",
    "rgba(245, 158, 11, 0.18)",
  );

  const visualFreq = getVisualMetrica(
    "frequencia",
    "#a78bfa",
    "rgba(167, 139, 250, 0.18)",
  );

  const serieCTR = dataset.ctr || [];
  const serieFreq = dataset.frequencia || [];

  new Chart(canvas, {
    type: "line",
    data: {
      labels: dataset.labels || [],
      datasets: [
        {
          label: "CTR",
          data: serieCTR,
          borderColor: visualCTR.color,
          backgroundColor: visualCTR.background,
          pointBackgroundColor: visualCTR.color,
          pointBorderColor: visualCTR.color,
          tension: 0.35,
          fill: false,
          yAxisID: "y",
        },
        {
          label: "Frequência",
          data: serieFreq,
          borderColor: visualFreq.color,
          backgroundColor: visualFreq.background,
          pointBackgroundColor: visualFreq.color,
          pointBorderColor: visualFreq.color,
          tension: 0.35,
          fill: false,
          yAxisID: "y1",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: "index",
        intersect: false,
      },
      plugins: {
        legend: {
          labels: {
            color: "#cbd5e1",
          },
        },
      },
      scales: {
        x: {
          ticks: { color: "#94a3b8" },
          grid: { color: "rgba(148, 163, 184, 0.08)" },
        },
        y: {
          position: "left",
          ticks: { color: "#94a3b8" },
          grid: { color: "rgba(148, 163, 184, 0.08)" },
        },
        y1: {
          position: "right",
          ticks: { color: "#94a3b8" },
          grid: { drawOnChartArea: false },
        },
      },
    },
  });
}

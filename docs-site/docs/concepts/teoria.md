---
title: Teoria
---

# Teoria

La qualita dell'intelligence dipende da tre livelli: identita prodotto, osservazioni nel tempo, e decisioni derivate.

## Matching Probabilistico

Il matching combina evidenze indipendenti. Una lettura pratica e:

$$
score = max(gtin, mpn, name, embedding, vision, llm)
$$

Il massimo e usato per privilegiare segnali forti come GTIN e MPN. Le soglie introducono un human-in-the-loop dove il rischio di falso positivo e piu alto.

## Time Series

Ogni prezzo normalizzato crea una serie temporale. Le feature di forecasting e anomaly detection lavorano su variazioni percentuali, trend e residui:

$$
residual_t = price_t - trend_t
$$

::: callout warning "Gotcha"
Un prezzo basso non e sempre un undercut reale. Shipping, disponibilita, seller, valuta, promozioni e bundle devono essere normalizzati prima di confrontare.
:::

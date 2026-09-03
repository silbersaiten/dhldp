# DHL Deutschepost 3.2.9 — Opis modułu

## Krótki opis

Twórz etykiety DHL Paket i Deutsche Post INTERNETMARKE z zamówień PrestaShop oraz obsługuj tracking, cło, manifesty i wspierane zwroty w Back Office.

## Pełny opis

DHL Deutschepost wiąże przewoźników PrestaShop z zakontraktowanymi produktami DHL i Deutsche Post. Pracownik przygotowuje przesyłkę w zamówieniu, tworzy i pobiera etykietę oraz zapisuje numer śledzenia. Opcjonalnie moduł zmienia status lub wysyła dołączony e-mail o przesyłce. Część DHL udostępnia domyślne paczki, usługi dodatkowe, eksport i zwroty; część Deutsche Post steruje INTERNETMARKE, PDF/PNG, pozycją na stronie, manifestem i listą wysyłkową.

Rozwiązanie ogranicza ponowne wpisywanie danych w portalu przewoźnika. Nie zastępuje umowy, nie liczy stawek checkout live i nie udostępnia usług, których konto nie obsługuje.

## Najważniejsze funkcje

- DHL Parcel Shipping API 2.1 dla nadawcy w Niemczech.
- Deutsche Post INTERNETMARKE REST jako PDF lub PNG.
- Mapowanie carrierów na produkty/participation.
- Pojedyncze i wspierane zbiorcze etykiety DHL.
- Tracking w zamówieniu i chroniony cron.
- Opcjonalny status/e-mail, cło produktu i dane eksportowe.
- Formaty, wymiary i domyślne usługi DHL.
- Packstation/Postfiliale z opcjonalną mapą Google.
- Wspierane zwroty DHL i integracja RMA.
- Manifesty/listy, gdy pozwala produkt/API.
- Świadoma konfiguracja i aktywacja multistore.
- Lokalna dokumentacja w sześciu językach.

## Zastosowanie i typowy przebieg

Moduł jest przeznaczony dla sklepów wysyłających zakontraktowanym DHL Paket z Niemiec albo korzystających z INTERNETMARKE. Administrator wybiera kontekst, zapisuje konto/nadawcę, produkty i przewoźników. Magazyn sprawdza przesyłkę, tworzy etykietę, a moduł zapisuje tracking i wykonuje wyłącznie wybrane działania. Manifest, zwrot i tracking uruchamia się zgodnie z potrzebą i możliwościami umowy.

## Korzyści

- Mniej przepisywania zamówień i adresów.
- Etykiety i tracking powiązane z zamówieniem.
- Kontrolowane wartości magazynowe, produkty, wymiary i formaty.
- DHL Paket oraz INTERNETMARKE w jednym module.
- Jawne ustawienia zgody, logów i wyjścia.
- Offline guide/opis w sześciu językach.

## Administracja i multistore

Sekcje **DHL settings**, **DHL DP settings**, **Information** i **DHL Manifest** porządkują konto, produkty/carriery, etykiety, usługi, zwroty, nadawcę, COD i INTERNETMARKE. Szybki start prowadzi do dokumentacji lokalnej, katalogu Silbersaiten i wsparcia.

Ustawienia obsługują wszystkie sklepy, grupę lub jeden sklep oraz aktywację w kontekście. Część danych pozostaje globalna/wspólna: formaty i wersja PPL, pobrane produkty, cło produktu i tabele bez bezpośredniej kolumny sklepu. Manifest/Information wymagają jednego sklepu. Nie należy obiecywać pełnej fizycznej izolacji danych.

## Prywatność, usługi i zgodność

Żądania mogą przekazywać tożsamość, adresy, zawartość/wartości, referencje, e-mail i telefon. Przy wyłączonej zgodzie e-mail i telefon są domyślnie przekazywane DHL. Przechowywane są konta, etykiety, paczki, tracking, RMA, cło, produkty/ceny, pliki i opcjonalne logi; deinstalacja ich nie usuwa.

Moduł może kontaktować DHL Shipping/Returns/Token/Location Finder/Tracking, Deutsche Post INTERNETMARKE/Tracking, Silbersaiten dla PPL, opcjonalnie Google Maps oraz pocztę/HP ePrint. Dokumentacja działa lokalnie.

Kod deklaruje PrestaShop od `1.6` do wersji bieżącej, a CHANGELOG wymienia PrestaShop 8 i 9. Brak formalnego zakresu PHP; opisano poprawki dla 7.2 i 8.4. Obecny kod nie jest zgodny z PHP 8.5 z powodu sygnatury `SoapClient::__doRequest()`. Wymagany jest test konkretnego środowiska.

## Ważne ograniczenia

- Nadawca DHL tylko Niemcy, API 2.1.
- Wymagane konta Live i produkty umowne.
- Brak silnika stawek checkout, kolejki i automatycznej retencji.
- Dostępność, ceny, terminy i akceptację ustalają DHL/Deutsche Post.
- Multistore ma ustawienia kontekstowe, ale nie każdy magazyn danych jest osobny.

## Główne zalety

- Etykiety i tracking wewnątrz PrestaShop.
- DHL Paket i INTERNETMARKE razem.
- Mapowanie produktów i praktyczne wartości magazynowe.
- Cło, usługi, manifesty i wspierane zwroty.
- Rzetelnie opisany multistore.
- Sześć języków offline i bezpośrednie wsparcie.

## Trzy krótkie teksty do karty

1. Twórz etykiety DHL Paket i INTERNETMARKE bezpośrednio z zamówień PrestaShop.
2. Połącz przewoźników PrestaShop z DHL/Deutsche Post, trackingiem, cłem i zwrotami.
3. Zarządzaj umowną wysyłką DHL i Deutsche Post w Back Office PrestaShop.

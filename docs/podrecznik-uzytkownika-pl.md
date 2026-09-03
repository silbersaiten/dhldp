# DHL Deutschepost 3.2.9 — Podręcznik użytkownika

## Zacznij tutaj

DHL Deutschepost łączy zamówienia PrestaShop z wysyłką DHL Paket dla klientów biznesowych oraz z Deutsche Post INTERNETMARKE. W Back Office tworzy etykiety, zapisuje numery śledzenia, obsługuje generowanie zbiorcze, wspierane manifesty i zwroty oraz może ułatwiać podawanie adresów Packstation/Postfiliale w kasie.

Moduł nie zawiera umowy z DHL, nie aktywuje produktów, nie oblicza stawek na żywo w kasie i nie określa zakresu umowy. Przed uruchomieniem trybu Live uzyskaj od DHL poniższe dane.

## Dane potrzebne przed konfiguracją

| Dane | Skąd je uzyskać | Zastosowanie |
| --- | --- | --- |
| Umowa biznesowa DHL | Opiekun handlowy DHL | Rzeczywiste przesyłki DHL Paket |
| Login i hasło GKP | Post & DHL Business Customer Portal | Uwierzytelnienie Live |
| EKP | Dane umowy w GKP lub dokumenty DHL | Identyfikacja konta |
| Numery rozliczeniowe (*Abrechnungsnummern*) | Pozycje umowy w GKP | Produkt/procedura i udział |
| Aktywacja DHL Retoure | Umowa DHL | Etykiety zwrotne, jeśli potrzebne |
| Konto Portokasse | Deutsche Post | INTERNETMARKE, jeśli używana |

Jeśli brakuje danych, skontaktuj się z osobą obsługującą umowę. Moduł nie może ustalić ani aktywować numerów umownych.

## Pierwsze logowanie do portalu DHL

1. Otwórz **Post & DHL Business Customer Portal (GKP)**: <https://geschaeftskunden.dhl.de/>.
2. Użyj osobistego loginu otrzymanego do umowy oraz hasła początkowego lub łącza resetowania.
3. Dokończ aktywację lub zmianę hasła wymaganą przez portal.
4. Jeśli dane nie dotarły, użyj **Forgot password/Passwort vergessen** albo skontaktuj się z administratorem konta bądź DHL. Pierwszy dostęp jest zwykle wysyłany po przetworzeniu rejestracji biznesowej.
5. Do produkcyjnego połączenia API DHL zaleca osobnego **systemowego użytkownika biznesowego**. Utwórz go lub zamów, gdy osobisty administrator ma już dostęp do GKP.

Użytkownik systemowy służy do API i nie może logować się do interfejsu WWW GKP. Zachowaj co najmniej jedno osobiste konto administratora. Nie wpisuj client ID ani client secret z DHL Developer Portal — moduł nie ma takich pól i w trybie Live używa skonfigurowanych danych GKP.

## Gdzie znaleźć EKP, produkt i udział

W GKP otwórz obszar umowy — zwykle **Vertragsdaten > Vertragspositionen** — i znajdź **Abrechnungsnummer** każdego używanego produktu. Nazwy menu mogą się zmienić; istotny jest numer przypisany do pozycji umowy.

Numer rozliczeniowy DHL Paket ma 14 znaków:

`1234567890 01 01`

| Część | Długość | Przykład | Pole w module |
| --- | --- | --- | --- |
| EKP | 10 znaków | `1234567890` | **EKP** |
| Procedura/produkt | 2 znaki | `01` | Wybierz odpowiedni **produkt DHL** |
| Udział (*Participation*) | 2 znaki | `01` | Pole **Participation** obok produktu |

Nie wklejaj całego numeru do pola Participation i nie wpisuj tam środkowego kodu produktu.

| Procedura | Produkt w module |
| --- | --- |
| `01` | DHL Paket |
| `53` | DHL Paket International |
| `54` | DHL Europaket |
| `62` | DHL Kleinpaket |
| `66` | Warenpost International |

Dodaj tylko produkty z umowy. Jeden produkt może mieć kilka udziałów. DHL może przydzielić wartość numeryczną lub alfanumeryczną, lecz osobne pole **Return participation** w tej wersji przyjmuje dokładnie dwie cyfry.

Dla zwrotów użyj udziału z właściwej pozycji DHL Retoure. `01` jest częste, ale nie gwarantowane. Stara dokumentacja opisywała osobne dane Retoure Portal i portal ID; obecna wersja nie ma tych pól i nie korzysta z dawnego logowania.

## Wymagania i zgodność

- PrestaShop: moduł deklaruje 1.6 jako minimum, a uruchomioną wersję PrestaShop jako maksimum. CHANGELOG zawiera zmiany dla PrestaShop 8 i 9.
- PHP: brak formalnego zakresu. Odnotowano poprawki dla PHP 7.2 i 8.4. Obecny kod nie działa z PHP 8.5 z powodu sygnatury `SoapClient::__doRequest()`; do czasu adaptacji użyj zgodnej wersji, np. PHP 8.4.
- Środowisko: wymagane są wychodzące HTTPS, cURL, JSON i mbstring; katalogi `logs`, `pdfs` i `data` muszą być zapisywalne.
- DHL: kraj nadawcy to obecnie tylko Niemcy (`DE`), a wersja API wysyłkowego jest ustawiona na `2.1`.
- Konta: Live wymaga danych GKP i zakontraktowanych produktów; INTERNETMARKE wymaga Portokasse.

Wykonaj kopię plików i bazy, przetestuj na stagingu, sprawdź HTTPS oraz ustal mapowanie przewoźników PrestaShop. W multistore wybierz właściwy kontekst przed zapisem.

## Instalacja i aktualizacja

### Nowa instalacja

1. W **Moduły > Menedżer modułów** wybierz przesłanie modułu i wskaż ZIP wydania.
2. Zainstaluj **DHL Deutschepost** i otwórz konfigurację.
3. Wykonaj pierwsze połączenie opisane poniżej.

### Aktualizacja z zachowaniem danych

Prześlij nowy ZIP albo wdroż cały katalog `dhldp` na istniejący. Nie odinstalowuj modułu. Uruchom aktualizację modułu, gdy PrestaShop ją zaproponuje. Standardowa ścieżka upgrade zachowuje istniejące klucze i dane.

Wyczyść cache PrestaShop i przeglądarki tylko wtedy, gdy nadal widać stare szablony, tłumaczenia lub zasoby.

## Pierwsze połączenie z DHL

1. Otwórz **DHL settings**.
2. Wybierz **Sandbox**, aby poznać proces bez prawdziwej umowy; moduł ma wbudowane dane sandbox. **Live** używaj tylko produkcyjnie.
3. W Live wpisz login GKP/API, hasło oraz 10-znakowy EKP. Zapis sprawdza konto. Późniejsze gwiazdki oznaczają jedynie zapisane hasło.
4. W **DHL Products** dodaj każdy produkt umowny: dwie środkowe cyfry Abrechnungsnummer wskazują produkt, a dwie ostatnie wpisz jako Participation.
5. W **Carriers** przypisz przewoźników PrestaShop do właściwej kombinacji. Akcje DHL pojawiają się tylko dla zmapowanego przewoźnika.
6. Wpisz nadawcę, rozdzielając ulicę i numer domu, albo dokładnie skopiuj referencję nadawcy GKP.
7. Wybierz format etykiety drukarki. Na pierwszy test wyłącz automatyczną zmianę statusu, akceptację ostrzeżeń, natychmiastowe zwroty i usługi dodatkowe.
8. Zapisz i utwórz etykietę dla zamówienia testowego.

Sandbox sprawdza przepływ modułu, nie zakres umowy Live. Przed regularną wysyłką wykonaj również kontrolowany test Live.

## Pierwsza etykieta DHL

1. Otwórz zamówienie testowe ze zmapowanym przewoźnikiem.
2. W obszarze DHL wybierz **Generate label**.
3. Sprawdź ulicę i numer odbiorcy, kod, kraj, wagę i wymiary. Przed ponowieniem użyj **Update delivery address**.
4. Dla eksportu sprawdź opis, wartość, kraj pochodzenia i kod taryfowy każdej pozycji.
5. Wybierz tylko usługi obsługiwane przez produkt i umowę i wyślij żądanie.
6. Otwórz PDF i zweryfikuj nadawcę, odbiorcę, produkt, format i numer przesyłki.
7. Sprawdź numer śledzenia oraz czy status lub wiadomość wykonały się tylko raz.

Numer domu zawsze przechowuj w osobnym polu. Połączona linia adresu jest częstą przyczyną odrzucenia.

## Etykiety zbiorcze

1. Na liście zamówień zaznacz te z poprawnym mapowaniem DHL.
2. Uruchom akcję zbiorczą **Generate DHL labels**.
3. Sprawdź każdy błąd osobno — zły adres, waga lub usługa mogą dotyczyć jednego zamówienia.
4. **Print last labels** pozwala ponownie pobrać ostatnią partię.

Zacznij od małej partii. Przetwarzanie zbiorcze nadal wymaga prawidłowych adresów, wag i danych celnych.

## Typowe problemy pierwszej etykiety

| Objaw | Przyczyna | Sprawdzenie |
| --- | --- | --- |
| Konto odrzucone przy zapisie | Zły użytkownik, hasło lub EKP Live; blokada | Sprawdź osobiste GKP i osobno użytkownika API, zresetuj hasło i porównaj EKP z umową. |
| Portal działa, moduł nie | Pomylenie użytkownika osobistego z systemowym lub wygasłe hasło | Użyj danych API; użytkownik systemowy nie loguje się do WWW GKP. |
| Brak akcji DHL | Brak mapowania przewoźnika w danym sklepie | Zapisz mapowanie w kontekście zamówienia. |
| Odrzucony produkt/udział | Wpisano złą część numeru | Znaki 11–12 = produkt, 13–14 = udział. |
| Odrzucony adres | Numer w polu ulicy, zły kod lub kraj | Rozdziel ulicę/numer i sprawdź format kraju. |
| Odrzucona waga/wymiary | Puste, zero, zła konwersja lub przekroczony limit | Sprawdź wagę i opakowanie; `1` dla kg, `0.001` dla gramów. |
| Błąd eksportu | Brak opisu, wartości, pochodzenia lub taryfy | Uzupełnij każdą pozycję; moduł sprawdza 6, 8 lub 10 cyfr. |
| Usługa niedostępna | Produkt, cel lub umowa jej nie obsługuje | Usuń usługę albo potwierdź umowę z DHL. |
| Brak PDF | `pdfs` bez prawa zapisu lub błąd API | Sprawdź prawa/miejsce i tymczasowo włącz maskowany log. |

## Ustawienia wymagające decyzji

- **Waga:** obliczaj z produktów tylko przy aktualnych wagach; konwersja `1` dla kg lub `0.001` dla gramów plus realne opakowanie.
- **Drukarka:** wybierz jej format; `100x70mm` jest tylko dla DHL Kleinpaket i Warenpost International.
- **Status i e-mail:** zacznij bez automatyzacji, potem wyklucz podwójne wiadomości.
- **Ostrzeżenia:** w pierwszych testach nie akceptuj ich automatycznie.
- **Usługi dodatkowe:** routing, GoGreen, GoGreen Plus, kontrola wieku, Premium i zwroty zależą od umowy i mogą kosztować.
- **Prywatność:** bez potwierdzenia moduł domyślnie wysyła do DHL e-mail i telefon. Ustal podstawę prawną i informację dla klienta.
- **Packstation/Postfiliale:** działają bez mapy; Google Maps jest opcjonalne i wymaga ograniczonego klucza.
- **Zwroty:** rozszerzone zwroty włączaj razem ze zwrotami PrestaShop po teście krajów. Natychmiastowa wysyłka etykiety jest domyślnie wyłączona.
- **Logi:** tylko diagnostycznie; logi i PDF-y nie są automatycznie usuwane.

## Pierwsza konfiguracja INTERNETMARKE

1. Zarejestruj się lub zaloguj do **Portokasse**: <https://portokasse.deutschepost.de/portokasse/>. Nowa rejestracja może wymagać kodu wysłanego listem.
2. Otwórz **DHL DP settings** i wpisz login oraz hasło Portokasse/INTERNETMARKE.
3. Przy pierwszym połączeniu Portokasse może poprosić o stałą zgodę dla aplikacji biznesowej. Sprawdź **Meine Daten > Geschäftsanwendungen**.
4. Pobierz formaty stron i w razie potrzeby uaktualnij listę PPL.
5. Przypisz tylko przewoźników Deutsche Post, wybierz produkt, `pdf` lub `png` i nadawcę.
6. Dla arkuszy PDF ustaw stronę, wiersz i kolumnę i wydrukuj próbę.

Jeśli logowanie nie działa, sprawdź Portokasse bezpośrednio i zresetuj tam hasło. Widoczny produkt nie gwarantuje uprawnień konta ani bieżącej ceny.

## Multistore

Ustawienia obsługują wszystkie sklepy, grupę i pojedynczy sklep z dziedziczeniem PrestaShop. Ogólne wartości zapisuj globalnie, a różne dane logowania, nadawców i mapowania — w konkretnym sklepie.

Ustawienia operacyjne są zwykle odczytywane dla sklepu zamówienia. Sześć tabel nie ma jednak bezpośredniego `id_shop`; dane celne produktów i lista Deutsche Post są współdzielone, a wersja PPL i formaty globalne. Manifest i Information wymagają pojedynczego sklepu. Testuj etykietę w każdym sklepie.

## Dane, prywatność, usługi i ograniczenia

Dane logowania i opcje są w konfiguracji PrestaShop; etykiety, paczki, zgody, cło i INTERNETMARKE w sześciu tabelach `dhldp_*`; pliki w `pdfs`; logi w `logs`; produkty Deutsche Post w `data/ppl.csv`.

Zależnie od usługi do DHL lub Deutsche Post mogą trafić nazwiska, adresy, zawartość, wartości, referencje, e-mail i telefon. Opcjonalne integracje: DHL Location Finder, Google Maps, `prestamodule.silberserver.de` dla PPL, poczta sklepu i HP ePrint. Lokalna dokumentacja nie wymaga zewnętrznych zasobów.

Kraj nadawcy DHL jest ograniczony do Niemiec, API do 2.1; brak stawek checkout i workera. `cron_track.php` aktualizuje śledzenie z tajnym kluczem — chroń URL. Dawny nadawca austriacki, osobny Retoure Portal i ręczny stary URL śledzenia nie dotyczą tej wersji.

Odinstalowanie usuwa zakładki i hooki, ale zachowuje tabele, konfigurację, etykiety, logi i listę produktów. Usuń je ręcznie po kopii tylko, gdy potrzebne jest pełne skasowanie.

## Lista kontrolna przed produkcją

- Konto Live przechodzi sprawdzenie z właściwym użytkownikiem API i EKP.
- Każdy przewoźnik wskazuje zakontraktowany produkt i udział.
- Nadawca, ulica/numer, konwersja wagi i format wydruku są sprawdzone.
- Osobno przetestowano przesyłkę krajową, potrzebny eksport i zwroty.
- Statusy, e-maile i zgoda odpowiadają polityce sklepu.
- Każdy kontekst multistore ma własny test.
- Log diagnostyczny jest wyłączony.


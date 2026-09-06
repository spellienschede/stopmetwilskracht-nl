# SPF/DKIM Configuratie voor Cloud86

## SPF Record Configuratie

SPF (Sender Policy Framework) vertelt andere mailservers dat jouw server namens jouw domein mag mailen.

### Stappen:

1. **Log in bij Cloud86** en ga naar je DNS beheer
2. **Voeg een TXT record toe** voor je domein `grippartner.nl`:

```
Type: TXT
Name: @ (of grippartner.nl)
Value: v=spf1 a mx ~all
TTL: 3600 (of automatisch)
```

**Uitleg:**
- `v=spf1` = SPF versie 1
- `a` = Het A-record van je domein mag mailen
- `mx` = Je MX records mogen mailen
- `~all` = Alle andere servers worden "soft fail" (niet blokkeren, maar markeren als verdacht)

**Als je een specifieke mailserver gebruikt:**
Als Cloud86 een specifieke mailserver gebruikt, moet je die toevoegen:
```
v=spf1 a mx include:cloud86.nl ~all
```

**Als je Gmail/Google Workspace gebruikt:**
```
v=spf1 include:_spf.google.com ~all
```

## DKIM Configuratie

DKIM (DomainKeys Identified Mail) voegt een digitale handtekening toe aan je emails.

### Stappen:

1. **Vraag bij Cloud86 support** om DKIM records voor je domein
2. Cloud86 genereert meestal een DKIM key pair voor je
3. **Voeg het DKIM record toe** aan je DNS:

```
Type: TXT
Name: default._domainkey (of wat Cloud86 aangeeft, bijv. selector1._domainkey)
Value: [Dit krijg je van Cloud86, ziet eruit als:]
v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC...
TTL: 3600
```

**Belangrijk:** De exacte naam en waarde krijg je van Cloud86 support.

## DMARC Record (Optioneel maar Aanbevolen)

DMARC vertelt andere mailservers wat ze moeten doen met emails die niet door SPF/DKIM validatie komen.

### Stappen:

1. **Voeg een DMARC record toe:**

```
Type: TXT
Name: _dmarc
Value: v=DMARC1; p=quarantine; rua=mailto:info@grippartner.nl
TTL: 3600
```

**Uitleg:**
- `v=DMARC1` = DMARC versie 1
- `p=quarantine` = Emails die falen gaan naar spam (gebruik `p=none` voor testen, `p=reject` voor productie)
- `rua=mailto:info@grippartner.nl` = Stuur rapporten naar dit adres

**Voor testen (aanbevolen eerst):**
```
v=DMARC1; p=none; rua=mailto:info@grippartner.nl
```

## Verificatie

Na het toevoegen van de records:

1. **Wacht 24-48 uur** voor DNS propagatie
2. **Test je SPF record:**
   - Gebruik: https://mxtoolbox.com/spf.aspx
   - Voer `grippartner.nl` in
3. **Test je DKIM:**
   - Stuur een test email naar jezelf
   - Check de email headers voor "DKIM-Signature"
4. **Test je DMARC:**
   - Gebruik: https://mxtoolbox.com/dmarc.aspx

## Contact Cloud86 Support

Als je hulp nodig hebt:
- **Email:** support@cloud86.nl
- **Vraag specifiek:** "Ik wil SPF en DKIM records configureren voor grippartner.nl om email deliverability te verbeteren"

Zij kunnen je precies vertellen welke records je nodig hebt voor hun mailserver setup.

## Belangrijk voor PHP mail()

**Let op:** PHP's `mail()` functie werkt, maar voor betere deliverability bij Cloud86:

1. **Check of Cloud86 een SMTP server heeft** die je kunt gebruiken
2. **Overweeg PHPMailer** met SMTP authenticatie in plaats van `mail()`
3. **Vraag Cloud86** naar hun aanbevolen mail configuratie

Als je veel emails verstuurt (>100 per dag), is SMTP meestal betrouwbaarder dan `mail()`.







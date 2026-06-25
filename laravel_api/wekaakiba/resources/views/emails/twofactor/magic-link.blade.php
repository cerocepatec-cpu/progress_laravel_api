@component('mail::message')
# 🔐 Vérification de sécurité

Bonjour **{{ optional($request->user)->name ?? 'Cher utilisateur' }}**,

Nous avons détecté une tentative d’action sensible sur votre compte.

### 📌 Détails de la connexion
- 📍 **IP** : {{ $request->ip_address ?? 'Inconnue' }}
- 🌐 **Navigateur** : {{ $request->browser ?? 'Inconnu' }}
- 🌍 **Localisation** : {{ $request->city ?? '—' }}, {{ $request->country ?? '—' }}

---

@component('mail::button', ['url' => $url])
✅ Confirmer l’action
@endcomponent

⏳ **Ce lien expire dans 10 minutes.**

---

### ❌ Ce n’était pas vous ?
Ignorez cet email.  
Aucune action ne sera effectuée sans validation.

Merci,  
**L’équipe Sécurité**

@endcomponent

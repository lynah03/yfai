# YFAI Partner Recommendation API

The YFAI Partner Recommendation API allows a brand or partner to send quiz answers and receive perfume recommendations from its own catalog.

For example, if Maison Cipro calls the API, the response will only contain Maison Cipro perfumes.

---

## Authentication

All API requests must include a valid JWT token.

Example:

Authorization: Bearer YOUR_JWT_TOKEN

To get a token, call:

POST /api/login

Request body:

{
"email": "admin@yfai.com",
"password": "admin"
}

Response example:

{
"status": "ok",
"success": true,
"token": "YOUR_JWT_TOKEN",
"refresh_token": "YOUR_REFRESH_TOKEN"
}

In production, partner authentication will later be handled with dedicated API keys per brand.

---

## Endpoint

POST /api/partners/{customerName}/recommendations

Example:

POST /api/partners/Maison%20Cipro/recommendations

customerName must match an existing brand name in the YFAI database.

---

## Request Body

{
"preferred_notes": ["Incense", "Leather", "Oud"],
"preferred_brands": ["Maison Cipro"],
"concentration": "PARFUM",
"budget_min": 200,
"budget_max": 300,
"limit": 5,
"offset": 0,
"maxReasons": 8
}

---

## Request Fields

preferred_notes
Type: array of strings
Required: no
Description: Notes liked by the user.

preferred_brands
Type: array of strings
Required: no
Description: Brands liked by the user.

concentration
Type: string
Required: no
Description: Preferred concentration, for example PARFUM or EAU_DE_PARFUM.

budget_min
Type: number
Required: no
Description: Minimum budget in euros.

budget_max
Type: number
Required: no
Description: Maximum budget in euros.

limit
Type: integer
Required: no
Description: Number of recommendations to return. Default is 5, maximum is 50.

offset
Type: integer
Required: no
Description: Pagination offset. Default is 0.

maxReasons
Type: integer
Required: no
Description: Maximum number of scoring reasons returned per perfume. Default is 8, maximum is 20.

---

## Example cURL Request

curl -X POST "http://127.0.0.1:8000/api/partners/Maison%20Cipro/recommendations" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_JWT_TOKEN" \
-d '{
"preferred_notes": ["Incense", "Leather", "Oud"],
"preferred_brands": ["Maison Cipro"],
"concentration": "PARFUM",
"budget_min": 200,
"budget_max": 300,
"limit": 5
}'

---

## Successful Response

{
"ok": true,
"customer": {
"id": 80,
"name": "Maison Cipro",
"country": "France"
},
"count": 2,
"limit": 5,
"offset": 0,
"results": [
{
"perfumeId": 112,
"brandId": 80,
"brand": "Maison Cipro",
"name": "Nero",
"score": 5.91,
"reasons": [
"+0.87 Incense",
"+0.87 Leather",
"+0.74 Oud",
"+2.40 marque Maison Cipro"
]
},
{
"perfumeId": 111,
"brandId": 80,
"brand": "Maison Cipro",
"name": "Caesar",
"score": 4.57,
"reasons": [
"+1.01 Leather",
"+2.40 marque Maison Cipro"
]
}
]
}

---

## Error Responses

### Unknown Brand

If the brand does not exist:

{
"ok": false,
"error": "unknown_customer",
"message": "Unknown brand/customer \"Fake Brand\"."
}

HTTP status: 404 Not Found

---

### Invalid JSON

If the request body is not valid JSON:

{
"ok": false,
"error": "invalid_json",
"message": "Request body must be valid JSON."
}

HTTP status: 400 Bad Request

---

### Missing or Expired Token

If the JWT token is missing:

{
"code": 401,
"message": "JWT Token not found"
}

If the JWT token is expired:

{
"code": 401,
"message": "Expired JWT Token"
}

HTTP status: 401 Unauthorized

---

## Current Behavior

The API always filters recommendations by the brand provided in the URL.

This means:

- Maison Cipro receives only Maison Cipro perfumes.
- Tom Ford receives only Tom Ford perfumes.
- KILIAN Paris receives only KILIAN Paris perfumes.

This is the expected behavior for a brand integration.

---

## Legacy Endpoint

The previous route still works for backward compatibility:

POST /api/brandsApi/{customerName}/recommandation

However, new integrations should use:

POST /api/partners/{customerName}/recommendations

---

## Future Improvements

The following improvements are planned for a later version:

1. Dedicated API keys per brand or partner.
2. Brand slugs instead of brand names in URLs.
3. Partner API logs and analytics.
4. Better request validation with a dedicated DTO.
5. More detailed integration examples for partner websites.
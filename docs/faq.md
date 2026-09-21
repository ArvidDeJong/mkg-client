---
title: "FAQ"
nav_order: 8
description: "Short answers about darvis/mkg-client and the MKG REST API: what it is, cost, versions, the base URL and login, 401 versus 403, paging, testing and safety."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}

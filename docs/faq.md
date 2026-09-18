---
title: FAQ
nav_order: 7
description: Short answers about the MKG REST API and darvis/mkg-client, from the base URL and the form login to 401 versus 403 and paging.
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}

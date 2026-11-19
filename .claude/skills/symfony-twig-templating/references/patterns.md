# Patterns Twig

## Page avec layout
```twig
{% extends 'base.html.twig' %}

{% block title %}{{ page_title }}{% endblock %}

{% block body %}
    <main>
        <h1>{{ page_title }}</h1>
    </main>
{% endblock %}
```

## Collection avec état vide
```twig
<ul>
    {% for item in items %}
        <li>{{ item.name }}</li>
    {% else %}
        <li>Aucun élément.</li>
    {% endfor %}
</ul>
```

## Fragment avec contexte explicite
```twig
{{ include('product/_card.html.twig', { product: product }, with_context = false) }}
```

## Lien vers une route
```twig
<a href="{{ path('app_product_show', { id: product.id }) }}">
    {{ product.name }}
</a>
```

## Formulaire Symfony
```twig
{{ form_start(form) }}
    {{ form_errors(form) }}
    {{ form_row(form.name) }}
    <button type="submit">Enregistrer</button>
{{ form_end(form) }}
```

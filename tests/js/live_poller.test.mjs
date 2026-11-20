// Tests de la boucle de rafraîchissement : node --test tests/js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { LivePoller } from '../../assets/lib/live_poller.js';

/** Document, horloge et fetch simulés : aucune attente réelle. */
function environnement() {
    const ecouteurs = new Map();
    const doc = {
        hidden: false,
        addEventListener: (t, f) => ecouteurs.set(t, f),
        removeEventListener: (t) => ecouteurs.delete(t),
        basculer(hidden) { this.hidden = hidden; ecouteurs.get('visibilitychange')?.(); },
    };
    let id = 0;
    const minuteurs = new Map();
    const timers = {
        setTimeout: (f, ms) => { minuteurs.set(++id, { f, ms }); return id; },
        clearTimeout: (i) => minuteurs.delete(i),
    };
    const appels = [];
    let reponse = () => ({ ok: true, json: async () => ({ missions: [] }) });
    const fetchImpl = (url, opts) => { appels.push({ url, opts }); return Promise.resolve(reponse()); };
    const vider = () => new Promise((r) => setImmediate(r));
    const avancer = async () => {
        const [cle, m] = [...minuteurs.entries()][0] ?? [];
        if (!cle) return null;
        minuteurs.delete(cle);
        m.f();
        await vider();
        return m.ms;
    };

    return { doc, timers, minuteurs, appels, fetchImpl, vider, avancer, repondre: (f) => { reponse = f; } };
}

test('une requête immédiate puis une seule boucle de 3 s', async () => {
    const env = environnement();
    const recus = [];
    const p = new LivePoller({ url: () => '/missions/live-status?ids=1,2', onData: (d) => recus.push(d), ...env });
    p.start();
    p.start(); // second démarrage ignoré
    await env.vider();
    assert.equal(env.appels.length, 1);
    assert.equal(env.minuteurs.size, 1, 'jamais deux boucles');
    assert.equal(await env.avancer(), 3000);
    assert.equal(env.appels.length, 2);
    assert.equal(env.minuteurs.size, 1);
    assert.equal(recus.length, 2);
    p.stop();
    assert.equal(env.minuteurs.size, 0);
});

test('arrêt quand l\'onglet est masqué, reprise immédiate au retour', async () => {
    const env = environnement();
    const p = new LivePoller({ url: () => '/x', onData: () => {}, ...env });
    p.start();
    await env.vider();
    env.doc.basculer(true);
    assert.equal(env.minuteurs.size, 0, 'aucune requête planifiée en arrière-plan');
    const avant = env.appels.length;
    env.doc.basculer(false);
    await env.vider();
    assert.equal(env.appels.length, avant + 1, 'actualisation immédiate');
    assert.equal(env.minuteurs.size, 1);
    p.stop();
});

test('la requête précédente encore active est annulée', async () => {
    const env = environnement();
    env.repondre(() => new Promise(() => {})); // ne répond jamais
    const p = new LivePoller({ url: () => '/x', onData: () => {}, ...env });
    p.start();
    const premier = env.appels[0].opts.signal;
    p.reprendre();
    assert.equal(premier.aborted, true);
    assert.equal(env.appels[1].opts.signal.aborted, false);
    p.stop();
    assert.equal(env.appels[1].opts.signal.aborted, true);
});

test('aucun appel sans composant à actualiser', async () => {
    const env = environnement();
    const p = new LivePoller({ url: () => null, onData: () => {}, ...env });
    p.start();
    await env.vider();
    assert.equal(env.appels.length, 0);
    assert.equal(env.minuteurs.size, 1, 'nouvel essai plus tard (des fiches peuvent apparaître)');
    p.stop();
});

test('erreurs réseau : pas d\'exception, délai progressif, retour à 3 s après succès', async () => {
    const env = environnement();
    const erreurs = [];
    env.repondre(() => { throw new TypeError('Failed to fetch'); });
    const p = new LivePoller({ url: () => '/x', onData: () => {}, onError: (e, n) => erreurs.push(n), ...env });
    p.start();
    await env.vider();
    assert.deepEqual([await env.avancer(), await env.avancer(), await env.avancer()], [3000, 6000, 12000]);
    assert.deepEqual(erreurs, [1, 2, 3, 4]);
    env.repondre(() => ({ ok: false, status: 500, json: async () => ({}) }));
    assert.equal(await env.avancer(), 24000);
    for (let i = 0; i < 5; i++) await env.avancer();
    assert.equal([...env.minuteurs.values()][0].ms, 30000, 'plafonné');
    env.repondre(() => ({ ok: true, json: async () => ({}) }));
    await env.avancer();
    assert.equal([...env.minuteurs.values()][0].ms, 3000);
    p.stop();
});

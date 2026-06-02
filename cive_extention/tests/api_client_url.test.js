const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

async function loadClientWithBase(apiBaseUrl) {
  let capturedUrl = null;

  const context = {
    console,
    setTimeout,
    clearTimeout,
    URL,
    URLSearchParams,
    AbortController,
    chrome: {
      runtime: {
        sendMessage() {
          throw new Error('background should not be used for same-origin requests');
        },
      },
    },
    window: {
      location: {
        origin: 'https://cive.consulmed.me',
      },
      configCIVE: {
        ready: Promise.resolve(),
        get() {
          return {
            apiBaseUrl,
            apiTimeoutMs: 1000,
            apiMaxRetries: 0,
            apiRetryDelayMs: 0,
            apiCredentialsMode: 'include',
          };
        },
      },
    },
    fetch: async (url) => {
      capturedUrl = url;
      return {
        ok: true,
        json: async () => ({success: true}),
      };
    },
  };

  vm.createContext(context);
  const source = fs.readFileSync(
    path.resolve(__dirname, '../js/api_client.js'),
    'utf8',
  );
  vm.runInContext(source, context);

  await context.window.CiveApiClient.post('/solicitudes/guardar.php', {
    body: {hcNumber: '123', form_id: '456', solicitudes: []},
  });

  return capturedUrl;
}

(async () => {
  const url = await loadClientWithBase('https://cive.consulmed.me');
  assert.strictEqual(
    url,
    'https://cive.consulmed.me/api/solicitudes/guardar.php',
  );

  const alreadyApiUrl = await loadClientWithBase('https://cive.consulmed.me/api');
  assert.strictEqual(
    alreadyApiUrl,
    'https://cive.consulmed.me/api/solicitudes/guardar.php',
  );
})().catch((error) => {
  console.error(error);
  process.exit(1);
});

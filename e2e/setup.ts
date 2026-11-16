import { fileURLToPath } from 'url';
import { execFileSync } from 'child_process';

export default function setup() {
    execFileSync('php', [fileURLToPath(new URL('./fixtures/suite-fixture.php', import.meta.url))], {
        input: JSON.stringify({ action: 'setup', run: process.env.E2E_RUN, password: process.env.E2E_PASSWORD }),
        stdio: ['pipe', 'pipe', 'pipe'],
    });
}

import { fileURLToPath } from 'url';
import { execSync as execute, execFileSync, ExecSyncOptions } from 'child_process';

export function suiteFixture(action: string, email?: string): Buffer {
    return execFileSync('php', [fileURLToPath(new URL('./fixtures/suite-fixture.php', import.meta.url))], {
        input: JSON.stringify({ action, email, run: process.env.E2E_RUN, password: process.env.E2E_PASSWORD }),
        stdio: ['pipe', 'pipe', 'pipe'],
    });
}

export function execSync(command: string, options?: ExecSyncOptions): Buffer {
    if (command.startsWith('php artisan e2e:cod-fixture')) {
        command += ` --seller=${process.env.E2E_SELLER_EMAIL} --buyer=${process.env.E2E_BUYER_EMAIL}`;
    }
    if (command.startsWith('php artisan e2e:seed-sale')) {
        command += ` --seller=${process.env.E2E_SELLER_EMAIL}`;
    }
    const result = execute(command, { ...options, stdio: 'pipe' }) as Buffer;
    if (command.startsWith('php artisan e2e:user ')) {
        suiteFixture('password', command.split(' ')[3]);
    }
    return result;
}

# Ohs Documents

Working documents of clients, branches and audits with workflow states, priority and protected download.

Hubleto app of the Ohs set (occupational health service, Slovakia). Namespace: `Hubleto\App\External\WaiBlue\OhsDocuments`.
Needs: `wai-blue/hubleto-ohs-core`, `wai-blue/hubleto-ohs-branches`, `wai-blue/hubleto-ohs-audits`.

## Install into a Hubleto project

```bash
composer config repositories.wai-blue/hubleto-ohs-documents vcs <repository-url>
composer require wai-blue/hubleto-ohs-documents:dev-main
php hubleto app install "Hubleto\App\External\WaiBlue\OhsDocuments"
npm run build
```

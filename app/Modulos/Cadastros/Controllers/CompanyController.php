<?php
namespace App\Modulos\Cadastros\Controllers;

use App\Core\View;

class CompanyController
{
    public function index(): void
    {
        require_role(['admin']);
        $db = db();
        $company = current_company();
        $companyId = current_company_id();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $displayName = trim($_POST['display_name'] ?? '');
            $logoUrl = trim($_POST['logo_url'] ?? '');
            $name = trim($_POST['name'] ?? '');
            if ($displayName === '') {
                $displayName = $name ?: ($company['display_name'] ?? 'Empresa');
            }
            $stmt = $db->prepare('UPDATE cad_empresas SET name = ?, display_name = ?, logo_url = ? WHERE id = ?');
            $stmt->execute([$name ?: $displayName, $displayName, $logoUrl, $companyId]);
            $_SESSION['company']['name'] = $name ?: $displayName;
            $_SESSION['company']['display_name'] = $displayName;
            $_SESSION['company']['logo_url'] = $logoUrl;
            flash('success', 'Dados da empresa atualizados.');
            header('Location: index.php?page=company');
            return;
        }

        $stmt = $db->prepare('SELECT * FROM cad_empresas WHERE id = ?');
        $stmt->execute([$companyId]);
        $company = $stmt->fetch() ?: $company;

        View::render('Cadastros', 'company', [
            'title' => 'Empresa',
            'company' => $company,
        ]);
    }
}

const fs = require('fs');
const path = require('path');

function updateVersion(type = 'patch') {
	// Legge il package.json
	const packagePath = path.join(__dirname, '..', 'package.json');
	const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
	const currentVersion = packageJson.version;

	// Split della versione
	let [major, minor, patch] = currentVersion.split('.').map(Number);

	// Incrementa la versione appropriata
	switch (type) {
		case 'major':
			major++;
			minor = 0;
			patch = 0;
			break;
		case 'minor':
			minor++;
			patch = 0;
			break;
		case 'patch':
		default:
			patch++;
			break;
	}

	const newVersion = `${major}.${minor}.${patch}`;

	// Aggiorna package.json
	packageJson.version = newVersion;
	fs.writeFileSync(packagePath, JSON.stringify(packageJson, null, '\t') + '\n');

	// Aggiorna il file principale del plugin
	const pluginPath = path.join(__dirname, '..', 'isigestsyncapi.php');
	let pluginContent = fs.readFileSync(pluginPath, 'utf8');

	// Aggiorna la versione nel plugin
	pluginContent = pluginContent.replace(/(\* Version:).*$/m, `$1 ${newVersion}`);

	// Aggiorna la costante ISIGEST_SYNC_API_VERSION
	pluginContent = pluginContent.replace(
		/define\('ISIGEST_SYNC_API_VERSION',\s*'[^']*'\);/,
		`define('ISIGEST_SYNC_API_VERSION', '${newVersion}');`
	);

	fs.writeFileSync(pluginPath, pluginContent);

	console.log(`Version updated to ${newVersion}`);
	return newVersion;
}

// Prendi l'argomento dalla riga di comando
const type = process.argv[2] || 'patch';
updateVersion(type);

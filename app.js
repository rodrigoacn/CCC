// app.js

const express = require('express');
const multer = require('multer');
const path = require('path');

const app = express();
const port = 3000;

// Configurar multer para manejar archivos subidos
const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    cb(null, 'uploads/');
  },
  filename: function (req, file, cb) {
    cb(null, Date.now() + path.extname(file.originalname));
  }
});

const upload = multer({ storage: storage });

// Ruta para subir una foto de perfil
app.post('/upload', upload.single('profilePicture'), (req, res) => {
  if (!req.file) {
    return res.status(400).send('No file uploaded.');
  }

  const filePath = req.file.path;
  const fileName = req.file.filename;

  // Aquí puedes agregar la lógica para guardar la foto en tu sistema de archivos

  res.send(`File uploaded successfully: ${fileName}`);
});

// Ruta para mostrar la foto de perfil
app.get('/profile-picture/:filename', (req, res) => {
  const filename = req.params.filename;
  const filePath = path.join(__dirname, 'uploads', filename);

  if (!fs.existsSync(filePath)) {
    return res.status(404).send('File not found.');
  }

  res.sendFile(filePath);
});

// Iniciar el servidor
app.listen(port, () => {
  console.log(`Server running on http://localhost:${port}`);
});

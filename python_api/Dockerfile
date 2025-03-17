FROM nvidia/cuda:11.8.0-devel-ubuntu20.04

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    python3-pip \
    git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Mettre à jour pip et installer packaging accelerate
RUN pip3 install --upgrade pip && \
    pip3 install packaging

# Installer Torch et ses packages depuis le dépôt CUDA (cela garantit que torch est installé avant flash-attn)
RUN pip3 install --no-cache-dir torch torchvision torchaudio --index-url https://download.pytorch.org/whl/cu118

# Copier et installer les autres dépendances (ici, requirements.txt doit EXCLURE torch, torchvision, torchaudio)
COPY requirements.txt /app/
RUN pip3 install -r requirements.txt

# Copier le reste du code
COPY . /app

# Ajouter le dossier d'installation local de pip au PATH pour que uvicorn soit trouvé
ENV PATH="/root/.local/bin:${PATH}"

EXPOSE 8000
CMD ["uvicorn", "api:app", "--host", "0.0.0.0", "--port", "8000"]
